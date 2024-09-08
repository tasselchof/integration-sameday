<?php

namespace Octava\Integrations\Sameday\Controller;

use Orderadmin\Application\Model\LoggerAwareInterface;
use Orderadmin\Application\Model\Manager\ConfigManagerAwareInterface;
use Orderadmin\Application\Model\Manager\GearmanManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Model\Manager\OrderadminManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ServiceManagerAwareInterface;
use Orderadmin\Application\Traits\ConfigManagerAwareTrait;
use Orderadmin\Application\Traits\GearmanManagerAwareTrait;
use Orderadmin\Application\Traits\LoggerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\Application\Traits\OrderadminManagerAwareTrait;
use Orderadmin\Application\Traits\ServiceManagerAwareTrait;
use Laminas\I18n\Translator\TranslatorAwareInterface;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Helper\TranslatorAwareTrait;
use Laminas\View\Model\ViewModel;
use Orderadmin\DeliveryServices\Entity\Source;
use Orderadmin\DeliveryServices\Exception\IntegrationException;
use Orderadmin\DeliveryServices\Model\IntegrationManagerAwareInterface;
use Orderadmin\DeliveryServices\Traits\IntegrationManagerAwareTrait;
use Users\Model\AuthManagerAwareInterface;
use Users\Model\UserManagerAwareInterface;
use Users\Traits\AuthManagerAwareTrait;
use Users\Traits\UserManagerAwareTrait;

class AppController extends AbstractActionController implements
    ConfigManagerAwareInterface,
    ServiceManagerAwareInterface,
    TranslatorAwareInterface,
    AuthManagerAwareInterface,
    ObjectManagerAwareInterface,
    OrderadminManagerAwareInterface,
    UserManagerAwareInterface,
    GearmanManagerAwareInterface,
    LoggerAwareInterface
{
    use ConfigManagerAwareTrait, ServiceManagerAwareTrait, TranslatorAwareTrait,
        AuthManagerAwareTrait, ObjectManagerAwareTrait, OrderadminManagerAwareTrait,
        UserManagerAwareTrait, GearmanManagerAwareTrait,
        LoggerAwareTrait;

    public function indexAction()
    {
        return new ViewModel();
    }
}
