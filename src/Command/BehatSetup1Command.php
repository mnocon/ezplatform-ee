<?php

namespace App\Command;

use Behat\Gherkin\Node\PyStringNode;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinitionCreateStruct;
use EzSystems\Behat\API\Facade\ContentTypeFacade;
use EzSystems\Behat\Core\Configuration\ConfigurationEditor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class BehatSetup1Command extends Command
{
    protected static $defaultName = 'ezplatform:behat:workflow-setup1';

    private $contentTypeFacade;
    /**
     * @var UserService
     */
    private $userService;
    /**
     * @var PermissionResolver
     */
    private $permissionResolver;

    public function __construct(ContentTypeFacade $contentTypeFacade, UserService $userService, PermissionResolver $permissionResolver)
    {
        parent::__construct();
        $this->contentTypeFacade = $contentTypeFacade;
        $this->userService = $userService;
        $this->permissionResolver = $permissionResolver;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->userService->loadUserByLogin('admin');
        $this->permissionResolver->setCurrentUserReference($user);
        $this->createContentTypes();
        $this->setConfiguration();
    }

    private function createContentTypes()
    {
        $fieldDefinitions1 = [
            ['Field Type' => 'Text line', 'Identifier' => 'name', 'Name' => 'Name', 'Required' => 'yes', 'Searchable' => 'yes', 'Translatable' => 'yes'],
            ['Field Type' => 'Rich text', 'Identifier' => 'description', 'Name' => 'Description', 'Required' => 'yes', 'Searchable' => 'no', 'Translatable' => 'yes'],
        ];

        $fieldDefinitions2 = [
            ['Field Type' => 'Text line', 'Identifier' => 'title', 'Name' => 'Title', 'Required' => 'yes', 'Searchable' => 'yes', 'Translatable' => 'yes'],
            ['Field Type' => 'Rich text', 'Identifier' => 'description', 'Name' => 'Description', 'Required' => 'yes', 'Searchable' => 'no', 'Translatable' => 'yes'],
            ['Field Type' => 'Landing Page', 'Identifier' => 'page', 'Name' => 'Page', 'Required' => 'yes', 'Searchable' => 'no', 'Translatable' => 'yes'],
        ];

        $this->contentTypeFacade->createContentType('CustomWorkflowContentType', 'customWorkflowContentType', 'Content', 'eng-GB', true, $this->parseFieldDefinitions($fieldDefinitions1));
        $this->contentTypeFacade->createContentType('CustomWorkflowContentTypeWithPage', 'customWorkflowContentTypeWithPage', 'Content', 'eng-GB', true, $this->parseFieldDefinitions($fieldDefinitions2));

    }

    private function parseFieldDefinitions($fieldTypeData): array
    {
        $parsedFields = [];
        $position = 10;

        foreach ($fieldTypeData as $fieldData) {
            $parsedFields[] = new FieldDefinitionCreateStruct([
                'fieldTypeIdentifier' => $this->contentTypeFacade->getFieldTypeIdentifierByName($fieldData['Field Type']),
                'identifier' => $fieldData['Identifier'],
                'names' => ['eng-GB' => $fieldData['Name']],
                'position' => $position,
                'isRequired' => $this->parseBool($fieldData['Required']),
                'isTranslatable' => $this->parseBool($fieldData['Translatable']),
                'isSearchable' => $this->parseBool($fieldData['Searchable']),
            ]);

            $position += 10;
        }

        return $parsedFields;
    }

    private function parseBool(string $value): bool
    {
        return $value === 'yes';
    }

    private function setConfiguration()
    {
        $configText = <<<'EOD'
        custom_workflow:
            name: Custom Workflow
            matchers:
                content_type: [customWorkflowContentType,customWorkflowContentTypeWithPage]
                content_status: draft
            stages:
                draft:
                    label: Draft
                    color: '#f15a10'
                review:
                    label: Technical review
                    color: '#10f15a'
                done:
                    label: Done
                    color: '#301203'
            initial_stage: draft
            transitions:
                to_review:
                    from: draft
                    to: review
                    label: To review
                    icon: '/bundles/ezplatformadminui/img/ez-icons.svg#comment'
                    notification:
                        user_group: 12
                to_done_from_draft:
                    from: draft
                    to: done
                    label: To done
                    icon: '/bundles/ezplatformadminui/img/ez-icons.svg#comment'
                to_done_from_review:
                    from: review
                    to: done
                    label: To done
                    icon: '/bundles/ezplatformadminui/img/ez-icons.svg#comment'
        EOD;
        $configFragment = new PyStringNode(explode(PHP_EOL, $configText), 0);

        $configPath = '/Users/mareknocon/Desktop/Sites/v3/config/packages/ezplatform.yaml';
        $configurationEditor = new ConfigurationEditor();
        $config = $configurationEditor->getConfigFromFile($configPath);
        $config = $configurationEditor->set($config, 'ezplatform.system.default.workflows', $this->parseConfig($configFragment));
        $configurationEditor->saveConfigToFile($configPath, $config);
    }

    private function parseConfig(PyStringNode $configFragment)
    {
        $cleanedConfig = '';

        // Remove indent from first line and adjust the rest
        $firstLine = $configFragment->getStrings()[0];
        $firstLineIndent = \strlen($firstLine) - \strlen(ltrim($firstLine));

        foreach ($configFragment->getStrings() as $line) {
            $cleanedConfig = $cleanedConfig . substr($line, $firstLineIndent) . PHP_EOL;
        }

        return Yaml::parse($cleanedConfig);
    }
}