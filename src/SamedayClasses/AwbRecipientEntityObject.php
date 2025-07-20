<?php

declare(strict_types=1);

namespace Octava\Integrations\Sameday\SamedayClasses;

use Sameday\Objects\PostAwb\Request\CompanyEntityObject;
use Sameday\Objects\PostAwb\Request\EntityObject;

use function array_merge;

/**
 * Awb recipient entity object.
 */
class AwbRecipientEntityObject extends EntityObject
{
    /** @var string */
    protected $email;

    /**
     * @param string|null $city
     * @param string|null $county
     * @param string|null $address
     * @param string|null $name
     * @param string|null $phone
     * @param string|null $email
     */
    public function __construct(
        $city = null,
        $county = null,
        $address = null,
        $name = null,
        $phone = null,
        $email = null,
        ?CompanyEntityObject $company = null,
        ?string $postalCode = null,
    ) {
        parent::__construct($city, $county, $address, $name, $phone, $company, $postalCode);

        $this->email = $email;
    }

    /**
     * @inheritDoc
     */
    public function getFields()
    {
        return array_merge(parent::getFields(), [
            'email' => $this->email,
        ]);
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return $this
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }
}
