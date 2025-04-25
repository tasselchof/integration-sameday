<?php
/**
 * Created by PhpStorm.
 * User: tasselchof
 * Date: 16.12.15
 * Time: 2:37
 */

namespace Octava\Integration\Sameday\Service;

use Laminas\Form\Element\Text;
use Laminas\Http\Client;
use Laminas\Http\Headers;
use Laminas\Http\Request;
use Laminas\Log\Logger;
use Orderadmin\Application\Form\Element\Select2;
use Orderadmin\Application\Model\Manager\ConfigManagerAwareInterface;
use Orderadmin\Application\Model\Manager\ObjectManagerAwareInterface;
use Orderadmin\Application\Traits\ConfigManagerAwareTrait;
use Orderadmin\Application\Traits\ObjectManagerAwareTrait;
use Orderadmin\DeliveryServices\Entity\DeliveryRequest;
use Orderadmin\DeliveryServices\Exception\DeliveryServiceException;
use Orderadmin\DeliveryServices\Model\DeliveryRequestManagerAwareInterface;
use Orderadmin\DeliveryServices\Model\DeliveryServiceInterface;
use Orderadmin\DeliveryServices\Model\Integration\IntegrationSettingsInterface;
use Orderadmin\DeliveryServices\Service\DeliveryServices\AbstractDeliveryService;
use Orderadmin\DeliveryServices\Traits\DeliveryRequestManagerAwareTrait;
use Orderadmin\DeliveryServices\Traits\DeliveryServiceV2Trait;

class Integration extends AbstractDeliveryService implements
    DeliveryServiceInterface,
    ObjectManagerAwareInterface,
    ConfigManagerAwareInterface,
    IntegrationSettingsInterface,
    DeliveryRequestManagerAwareInterface
{
    use ConfigManagerAwareTrait,
        ObjectManagerAwareTrait,
        DeliveryRequestManagerAwareTrait;


    const DELIVERY_SERVICE = 'sameday';

//    protected int $retry = 0;
//    protected $response;
//    protected $cache;
//    protected int $requestTimeout = 30;

//    protected ?AbstractSource $source;
    protected $widgetConfig;
    protected $url = 'ssapi.sameday.com';
    private $port;
    protected $key;
    protected $secret;

    private $trackingClient;
    private $username;
    private $password;
    private $response;
    private array $request;

    public function getIntegrationSettings(): array
    {
        return [
            [
                'name' => 'username',
                'type' => Text::class,
                'options' => [
                    'label' => 'Username',
                    'sub_group' => 'auth',
                    'required' => true,
                ],
            ],
            [
                'name' => 'password',
                'type' => Text::class,
                'options' => [
                    'label' => 'Password',
                    'sub_group' => 'auth',
                    'required' => true,
                ],
            ],
            [
                'name' => 'servicePoint',
                'type' => Text::class,
                'options' => [
                    'label' => 'Pickup point',
                    'required' => true,
                ],
            ]
        ];
    }

    public function getCronConfig(): array
    {
        return [
//            [
//                'type'      => 'gearman',
//                'service'   => Tracking::class,
//                'name'      => 'load-states',
//                'frequency' => '0 */5 * * *',
//                'method'    => 'loadStatesQueue',
//            ],
        ];
    }

    public function request(
        $path,
        $query = [],
        $method = Request::METHOD_GET,
        $debug = false
    ) {
        $allowedMethods = [
            Request::METHOD_GET,
            Request::METHOD_POST
        ];
        if (! in_array($method, $allowedMethods, false)) {
            throw new \Exception(
                sprintf(
                    'Method "%s" is not valid. Allowed methods are %s',
                    $method,
                    implode(', ', $allowedMethods)
                )
            );
        }

        $request = new Request();

        $url = $this->restUrl . $path;

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        if ($debug) {
            $this->getLogger()->debug($url);
        }

        $request->setUri($url);

        $header = new Headers();
        switch ($method) {
            case Request::METHOD_POST:
                $request->setMethod(Request::METHOD_POST);

                $request->setContent(
                    ! empty($this->getRequest()) ? $this->getRequest() : '{}'
                );

                $header->addHeaderLine(
                    'Content-Type',
                    'application/json'
                );
                break;
        }

        $request->setHeaders($header);

        $client = new Client(
            $url
        );

        $this->getLogger()->log(
            Logger::INFO,
            sprintf('Querying with %s', $method)
        );

        $response = $client->dispatch($request);

        if ($response->isSuccess()) {
            if ($debug) {
                $this->getLogger()->debug($url);
                $this->getLogger()->debug($this->getRequest());

                $this->getLogger()->debug($response->getBody());

                $this->getLogger()->debug($client->getLastRawRequest());
                $this->getLogger()->debug($client->getLastRawResponse());
            }

            $this->setResponse($response->getBody());
            $this->setRequest([]);

            return $this;
        } else {
            $this->getLogger()->debug($url);
            $this->getLogger()->debug($this->getRequest());

            $this->getLogger()->debug($response->getBody());

            $this->getLogger()->debug($client->getLastRawRequest());
            $this->getLogger()->debug($client->getLastRawResponse());

            $error = json_decode($response->getBody(), true);

            if (! empty($error)) {
                throw new DeliveryServiceException(
                    sprintf(
                        '%s (url: %s): %s',
                        $error['error'],
                        $url,
                        var_export($error['error']['message'], true)
                    )
                );
            } else {
                throw new DeliveryServiceException(
                    sprintf(
                        'Problem with request to %s: %s',
                        $url,
                        $response->getReasonPhrase()
                    )
                );
            }
        }
    }

    public function checkRate(DeliveryRequest $deliveryRequest)
    {
    }

    public function setUsername($username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setRequest(array $request): static
    {
        $this->request = $request;

        return $this;
    }

    private function getRequest(): bool|string|null
    {
        if (! empty($this->request)) {
            return json_encode($this->request);
        }

        return null;
    }

    public function setResponse($response): static
    {
        $this->response = $response;

        return $this;
    }
}
