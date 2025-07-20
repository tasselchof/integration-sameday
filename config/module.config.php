<?php

declare(strict_types=1);

use Octava\Integration\Sameday\Controller\AppController;
use Octava\Integration\Sameday\Service\Calculation;
use Octava\Integration\Sameday\Service\Integration;
use Octava\Integration\Sameday\Service\Labels;
use Octava\Integration\Sameday\Service\Payments;
use Octava\Integration\Sameday\Service\Shipment;
use Octava\Integration\Sameday\Service\V2\Shipment as ShipmentV2;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Entity\Rate;
use Orderadmin\DeliveryServices\Entity\ServicePoint;

return [
    'integrations'      => [
        'sameday' => [
            'name'     => 'Sameday',
            'services' => [
                'settings' => Octava\Integration\Sameday\Service\Settings::class,
                //                'products'          => Service\Products::class,
                //                'delivery-requests' => Service\DeliveryRequests::class,
                //                'orders'            => Service\Orders::class,
                //                'eav'               => Service\EavRegistry::class,
                //                'inventory'         => Service\Loader::class,
            ],
        ],
    ],
    'delivery-services' => [
        'sameday' => [
            'name'        => 'Sameday',
            'integration' => Integration::class,
            'cron'        => [
                'update-cities'         => [
                    'method'    => 'loadCities',
                    'timetable' => '0 21 */7 * *',
                    'params'    => [],
                ],
                'update-service-points' => [
                    'method'    => 'loadServicePoints',
                    'timetable' => '0 21 * * *',
                    'params'    => [],
                ],
            ],
            'api'         => [],
            'options'     => [
                'calculate' => false,
                'username'  => '[username]',
                'password'  => '[password]',
                'services'  => [],
            ],
            'services'    => [
                'shipment'      => Shipment::class,
                'shipmentV2'    => ShipmentV2::class,
                'labels'        => Labels::class,
                'calculationV2' => Calculation::class,
                'loader'        => [
                    Rate::class            => \Octava\Integration\Sameday\Service\Loader\Services::class,
                    ServicePoint::class    => \Octava\Integration\Sameday\Service\Loader\ServicePoints::class,
                    DeliveryRequest::class => \Octava\Integration\Sameday\Service\Loader\DeliveryRequests::class,
                ],
                'payments'      => Payments::class,
            ],
        ],
    ],
    'event-listeners'   => [
        \Octava\Integration\Sameday\Listener\PreprocessingTaskListener::class,
    ],
    'router'            => [
        'routes' => [
            'apps' => [
                'child_routes' => [
                    'sameday' => [
                        'type'          => 'Literal',
                        'options'       => [
                            'route'    => '/sameday',
                            'defaults' => [
                                'controller' => AppController::class,
                                'action'     => 'index',
                                'access'     => [
                                    'resource' => sprintf(
                                        '%s-%s',
                                        \Orderadmin\DeliveryServices\Module::MODULE_ID,
                                        Octava\Integration\Sameday\Module::INTEGRATION_ID
                                    ),
                                ],
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes'  => [
                            'default' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'       => '[/][:action][/:id]',
                                    'constraints' => [
                                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id'         => '[0-9a-zA-Z\*]+',
                                    ],
                                    'defaults'    => [
                                        'controller' => AppController::class,
                                        'action'     => 'index',
                                        'access'     => [
                                            'resource' => sprintf(
                                                '%s-%s',
                                                \Orderadmin\DeliveryServices\Module::MODULE_ID,
                                                Octava\Integration\Sameday\Module::INTEGRATION_ID
                                            ),
                                        ],
                                    ],
                                ],
                            ],
                            'labels'  => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'       => '/labels[/:id]',
                                    'constraints' => [
                                        'id' => '[0-9a-zA-Z\*]+',
                                    ],
                                    'defaults'    => [
                                        'controller' => \Octava\Integration\Sameday\Controller\LabelController::class,
                                        'action'     => 'index',
                                        'access'     => [
                                            'resource' => sprintf(
                                                '%s-%s',
                                                \Orderadmin\DeliveryServices\Module::MODULE_ID,
                                                Octava\Integration\Sameday\Module::INTEGRATION_ID
                                            ),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager'      => [
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
];
