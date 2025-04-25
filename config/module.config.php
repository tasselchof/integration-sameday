<?php

use Octava\Integration\Sameday\Controller\AppController;
use Octava\Integration\Sameday\Service\Calculation;
use Octava\Integration\Sameday\Service\Labels;
use Octava\Integration\Sameday\Service\Shipment;
use Octava\Integration\Sameday\Service\Integration;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\DeliveryServices\Entity\ServicePoint;
use Orderadmin\Integrations\Module;

return [
    'delivery-services'             => [
        'sameday' => [
            'name'        => 'Sameday',
            'integration' => Integration::class,
            'services'    => [
                'shipment' => Shipment::class,
                'labels' => Labels::class,
                'loader'            => [
                    Rate::class    => \Octava\Integration\Sameday\Service\Loader\Services::class,
                    ServicePoint::class => \Octava\Integration\Sameday\Service\Loader\ServicePoints::class,
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
                    \Octava\Integration\Sameday\Module::DELIVERY_SERVICE
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
        \Octava\Integration\Sameday\Module::DELIVERY_SERVICE => [

        ]
    ],
    'event-listeners'   => [
//        PreprocessingTaskListener::class,
    ],
    'input_filter_specs'            => [
    ],
];
