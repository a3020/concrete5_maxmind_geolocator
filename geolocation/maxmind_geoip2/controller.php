<?php

namespace Concrete\Package\MaxmindGeolocator\Geolocator\MaxmindGeoip2;

use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Geolocator\GeolocationResult;
use Concrete\Core\Geolocator\GeolocatorController;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use GeoIp2\Model\City;
use GeoIp2\Model\Country;
use GeoIp2\Model\Enterprise;
use IPLib\Address\AddressInterface;
use MaxmindGeolocator\Exception\InvalidConfigurationArgument;
use MaxmindGeolocator\Exception\MaxMindDatabaseUnavailable;
use Symfony\Component\HttpFoundation\ParameterBag;

class Controller extends GeolocatorController
{
    /**
     * {@inheritdoc}
     *
     * @see GeolocatorController::renderConfigurationForm()
     */
    public function renderConfigurationForm()
    {
        parent::renderConfigurationForm();
        $this->requireAsset('selectize');
    }

    /**
     * {@inheritdoc}
     *
     * @see GeolocatorController::saveConfigurationForm()
     */
    public function saveConfigurationForm(array $configuration, ParameterBag $data, ErrorList $error)
    {
        $valn = $this->app->make('helper/validation/numbers');
        $s = $data->get('maxmindgl-productid');
        $s = is_string($s) ? trim($s) : '';
        if ($s === '') {
            $error->add(t('Please specify the MaxMind product'));
        } else {
            $configuration['product-id'] = $s;
        }
        $s = $data->get('maxmindgl-databasepath');
        $s = is_string($s) ? trim($s) : '';
        if ($s === '') {
            $error->add(t('Please specify the location of the local MaxMind database'));
        } else {
            $configuration['database-path'] = $s;
        }
        $s = $data->get('maxmindgl-licensekey');
        $configuration['license-key'] = is_string($s) ? trim($s) : '';
        $s = $data->get('maxmindgl-userid');
        $s = is_string($s) ? trim($s) : '';
        if ($s === '') {
            $configuration['user-id'] = null;
        } elseif ($valn->integer($s)) {
            $configuration['user-id'] = (int) $s;
        } else {
            $error->add(t('Please specify a number for the MaxMind user ID'));
        }

        $s = $data->get('maxmindgl-protocol');
        if (!is_string($s) || !in_array($s, ['http', 'https'])) {
            $error->add(t('Please specify a valid value for the connection protocol'));
        } else {
            $configuration['protocol'] = $s;
        }
        $s = $data->get('maxmindgl-host');
        $s = is_string($s) ? trim($s) : '';
        if ($s === '') {
            $error->add(t('Please specify the host of the MaxMind server'));
        } else {
            $configuration['host'] = $s;
        }

        return $configuration;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Geolocator\GeolocatorController::geolocateIPAddress()
     */
    public function geolocateIPAddress(AddressInterface $address)
    {
        $result = parent::geolocateIPAddress($address);
        $exception = $result->getInnerException();
        if ($exception instanceof InvalidConfigurationArgument) {
            $result->setError(GeolocationResult::ERR_LIBRARYSPECIFIC, $exception->getMessage(), $exception);
        } elseif ($exception instanceof MaxMindDatabaseUnavailable) {
            $result->setError(GeolocationResult::ERR_LIBRARYSPECIFIC, $exception->getMessage(), $exception);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @see GeolocatorController::performGeolocation()
     */
    protected function performGeolocation(AddressInterface $address)
    {
        $reader = $this->app->make(Reader::class);
        $metadata = $reader->metadata();
        $result = new GeolocationResult();
        try {
            if (strpos($metadata->databaseType, 'City') !== false) {
                $this->cityToGeolocationResult($reader->city((string) $address), $result);
            } elseif (strpos($metadata->databaseType, 'Country') !== false) {
                $this->countryToGeolocationResult($reader->country((string) $address), $result);
            } elseif (strpos($metadata->databaseType, 'Enterprise') !== false) {
                $this->enterpriseToGeolocationResult($reader->enterprise((string) $address), $result);
            } else {
                $result->setError(GeolocationResult::ERR_LIBRARYSPECIFIC, t('Unsupported MaxMind database type: %s', $metadata->databaseType));
            }
        } catch (AddressNotFoundException $foo) {
        }

        return $result;
    }

    /**
     * @param City $data
     * @param GeolocationResult $result
     */
    private function cityToGeolocationResult(City $data, GeolocationResult $result)
    {
        $result
            ->setCityName($data->city->name)
            ->setStateProvinceCode($data->mostSpecificSubdivision->isoCode)
            ->setStateProvinceName($data->mostSpecificSubdivision->name)
            ->setPostalCode($data->postal->code)
            ->setCountryCode($data->country->isoCode)
            ->setCountryName($data->country->name)
            ->setLatitude($data->location->latitude)
            ->setLongitude($data->location->longitude)
        ;
    }

    /**
     * @param City $data
     * @param GeolocationResult $result
     */
    private function countryToGeolocationResult(Country $data, GeolocationResult $result)
    {
        $result
            ->setCountryCode($data->country->isoCode)
            ->setCountryName($data->country->name)
        ;
    }

    /**
     * @param Enterprise $data
     * @param GeolocationResult $result
     */
    private function enterpriseToGeolocationResult(Enterprise $data, GeolocationResult $result)
    {
        $result
            ->setCityName($data->city->name)
            ->setStateProvinceCode($data->mostSpecificSubdivision->isoCode)
            ->setStateProvinceName($data->mostSpecificSubdivision->name)
            ->setPostalCode($data->postal->code)
            ->setCountryCode($data->country->isoCode)
            ->setCountryName($data->country->name)
            ->setLatitude($data->location->latitude)
            ->setLongitude($data->location->longitude)
        ;
    }
}
