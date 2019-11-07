<?php

namespace App\Command;


use Behat\Gherkin\Node\TableNode;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\UserService;
use eZ\Publish\API\Repository\Values\User\Limitation;
use EzSystems\Behat\API\Facade\RoleFacade;
use EzSystems\Behat\API\Facade\UserFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BehatSetup2Command extends Command
{
    protected static $defaultName = 'ezplatform:behat:workflow-setup2';

    /**
     * @var UserService
     */
    private $userService;
    /**
     * @var PermissionResolver
     */
    private $permissionResolver;
    /**
     * @var UserFacade
     */
    private $userFacade;
    /**
     * @var RoleFacade
     */
    private $roleFacade;

    public function __construct(UserFacade $userFacade, RoleFacade $roleFacade, UserService $userService, PermissionResolver $permissionResolver)
    {
        parent::__construct();
        $this->userFacade = $userFacade;
        $this->roleFacade = $roleFacade;
        $this->userService = $userService;
        $this->permissionResolver = $permissionResolver;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $user = $this->userService->loadUserByLogin('admin');
        $this->permissionResolver->setCurrentUserReference($user);


        $userGroupName = 'WorkflowEditors';
        $this->userFacade->createUserGroup($userGroupName);

        $this->createCreatorUser($userGroupName);
        $this->createPublisherUser($userGroupName);
    }

    private function createCreatorUser($userGroupName)
    {
        $this->userFacade->createUser('Creator', 'Workflow', $userGroupName);
        $creatorRoleName = 'creatorRole';
        $this->roleFacade->createRole($creatorRoleName);

        $policiesCreator = [
            ['content', 'read'],
            ['content', 'create'],
            ['content', 'read'],
            ['content', 'versionread'],
            ['content', 'edit'],
            ['section', 'assign'],
            ['user', 'login'],
        ];

        foreach ($policiesCreator as $policy) {
            $this->roleFacade->addPolicyToRole($creatorRoleName, $policy[0], $policy[1]);
        }

        $limitation = $this->parseLimitation('Workflow Transition', 'custom_workflow:to_review');
        $this->roleFacade->addPolicyToRole($creatorRoleName, 'workflow', 'change_stage', [$limitation]);

        $this->userFacade->assignUserToRole('Creator', $creatorRoleName);
    }

    private function createPublisherUser($userGroupName)
    {
        $this->userFacade->createUser('Publisher', 'Workflow', $userGroupName);
        $publisherRoleName = 'publisherRole';
        $this->roleFacade->createRole($publisherRoleName);

        $policiesPublisher = [
            ['content', 'read'],
            ['content', 'create'],
            ['content', 'read'],
            ['content', 'versionread'],
            ['section', 'assign'],
            ['user', 'login'],
        ];

        foreach ($policiesPublisher as $policy) {
            $this->roleFacade->addPolicyToRole($publisherRoleName, $policy[0], $policy[1]);
        }

        $limitation = $this->parseLimitation('Workflow Stage', 'custom_workflow:done');
        $this->roleFacade->addPolicyToRole($publisherRoleName, 'content', 'edit', [$limitation]);
        $this->roleFacade->addPolicyToRole($publisherRoleName, 'content', 'publish', [$limitation]);

        $this->userFacade->assignUserToRole('Publisher', $publisherRoleName);
    }

    private function parseLimitation($limitationType, $limitationValue): Limitation
    {
        $limitationParsers = $this->roleFacade->getLimitationParsers();

        foreach ($limitationParsers as $parser) {
            if ($parser->supports($limitationType)) {
                return $parser->parse($limitationValue);
            }
        }
    }
}