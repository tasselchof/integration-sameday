<?php

use Octava\Integrations\Sameday\Controller\AppController;
use Octava\Integrations\Sameday\Listener\PreprocessingTaskListener;
use Octava\Integrations\Sameday\Service\DeliveryServices\Calculation;
use Octava\Integrations\Sameday\Service\DeliveryServices\Labels;
use Octava\Integrations\Sameday\Service\DeliveryServices\Loader\DeliveryRequests;
use Octava\Integrations\Sameday\Service\DeliveryServices\Shipment;
use Octava\Integrations\Sameday\Service\Integration;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\Integrations\Module;

return [
    'delivery-services'             => [
        'sameday' => [
            'name'        => 'Sameday',
            'integration' => Integration::class,
            'services'    => [
                'shipmentV2' => Shipment::class,
                'labels' => Labels::class,
                'loader'            => [
                    DeliveryRequest::class => DeliveryRequests::class,
                ],
                'calculationV2' => Calculation::class,
            ],
        ],
    ],
    'access-system'                 => [
        'resources' => [
            [
                'name' => sprintf(
                    '%s-%s',
                    Module::MODULE_ID,
                    \Octava\Integrations\Sameday\Module::DELIVERY_SERVICE
                ),
            ],
        ],
    ],
    'router'                        => [
        'routes' => [
            'apps'                                                           => [
                'child_routes' => [
                    'sameday' => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/sameday',
                            'defaults' => [
                                'controller' => AppController::class,
                                'action'     => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'default' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'       => '[/][:action][/:id][/:subAction][/:subId]',
                                    'constraints' => [
                                        'action'    => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id'        => '[a-zA-Z0-9_-]*',
                                        'subAction' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'subId'     => '[0-9]+',
                                    ],
                                    'defaults'    => [
                                        'controller' => AppController::class,
                                        'action'     => 'index',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager'                  => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'eav_attributes'                => [
        \Octava\Integrations\Sameday\Module::DELIVERY_SERVICE => [

        ]
    ],
    'event-listeners'   => [
        PreprocessingTaskListener::class,
    ],
    'input_filter_specs'            => [
    ],
];
