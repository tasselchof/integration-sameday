<?php

declare(strict_types=1);

namespace Octava\Integration\Sameday\Service;

use Laminas\Form\Element\Text;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ViewHelperManagerAwareInterface;
use Orderadmin\Application\Settings\Objects\FormElement;
use Orderadmin\Application\Settings\Objects\Group;
use Orderadmin\Application\Settings\Settings as AppSettings;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\Application\Traits\ViewHelperManagerAwareTrait;
use Orderadmin\Integrations\Model\Feature\SettingsProviderInterface;
use Users\Validator\RoleAccessRights;

class Settings implements
    SettingsProviderInterface,
    ObjectManagerAwareInterface,
    ViewHelperManagerAwareInterface
{
    use ObjectManagerAwareTrait;
    use ViewHelperManagerAwareTrait;

    public function getParamsConfig(
        $attributes = [],
        $action = RoleAccessRights::ACCESS_ADD
    ): array {
        $settings = new AppSettings($this->getViewHelperManager());

        $authFields = new Group(
            'auth',
            'Authentication'
        );

        $authFields->addAttribute(
            new FormElement(
                new Text(
                    'username',
                    [
                        'label' => 'Username',
                    ]
                )
            )
        );

        $authFields->addAttribute(
            new FormElement(
                new Text(
                    'password',
                    [
                        'label' => 'Password',
                    ]
                )
            )
        );

        $settings->addObject($authFields);

        $generalSettingsGroup = new Group('general', 'General Options');

        $generalSettingsGroup->addAttribute(
            new FormElement(
                new Text(
                    'servicePoint',
                    [
                        'label' => 'Pickup point',
                    ]
                )
            )
        );

        $generalSettingsGroup->addAttribute(
            new FormElement(
                new Text(
                    'documents-type',
                    [
                        'label'       => 'Document Type',
                        'description' => 'Document type for labels (default: A6)',
                    ]
                )
            )
        );

        // Add all subgroups to Sameday general group
        $settings->addObject($generalSettingsGroup);

        return $settings->build();
    }

    /**
     * @return void
     */
    public function validateParams($data)
    {
        // TODO: Implement validateParams() method.
    }
}
