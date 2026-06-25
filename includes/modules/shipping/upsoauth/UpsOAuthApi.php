<?php
// -----
// API/Rate-generation interfaces that support shipping modules that use the
// UPS RESTful API with OAuth authentication.
//
// Last updated: v1.4.0
//
// Copyright 2023-2026, Vinos de Frutas Tropicales
//
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

class UpsOAuthApi extends base
{
    // -----
    // Constants that define the test and production endpoints for the API requests.
    //
    const ENDPOINT_TEST = 'https://wwwcie.ups.com/';
    const ENDPOINT_PRODUCTION = 'https://onlinetools.ups.com/';

    // -----
    // Constants used when making the various API requests to UPS; appended to the currently
    // configured endpoint.
    //
    const API_OAUTH_TOKEN = 'security/v1/oauth/token';
    const API_RATING = 'api/rating/v2409/Shoptimeintransit';    //- Gives *all* UPS shipping methods for a given From->To address.
    const API_RATING_SUBVERSION = '2409';

    protected $endpoint;

    protected $currencyCode;

    protected $packagingTypes;
    protected $pickupMethods;
    protected $serviceCodes;

    protected $debug;
    protected $logfile;

    // -----
    // This value indicates the API version, which is not necessarily the same as the version of the
    // shipping-module itself.  It'll be updated if any new methods are introduced or additional
    // parameters added to existing methods.
    //
    private $upsOAuthApiVersion = '1.4.0';

    // -----
    // Class constructor:
    //
    // - $endpoint_type: Identifies whether the test ('Test') or production (anything else) URL is to be used for API requests.
    // - $debug: Indicates whether ((bool)true) or not the class debug is to be enabled.
    // - $debug_logfile: Identifies the filename (including path) to which debug output is to be written.
    //
    public function __construct($endpoint_type, $debug, $debug_logfile)
    {
        $this->endpoint = ($endpoint_type === 'Test') ? self::ENDPOINT_TEST : self::ENDPOINT_PRODUCTION;

        $this->debug = ($debug === true);
        $this->logfile = $debug_logfile;
        $this->currencyCode = $this->zenConfig('DEFAULT_CURRENCY');

        $this->initializeValueMappings();
    }

    // -----
    // This method returns the *private* API version, which indicates the version
    // associated with class methods *in this base class*.  It cannot be overridden
    // by a class extension, but the value returned can be used by class extensions
    // to "do the right thing" if/when another method or method-parameter is introduced.
    //
    final public function getUpsOAuthApiVersion()
    {
        return $this->upsOAuthApiVersion;
    }

    protected function initializeValueMappings()
    {
        // -----
        // UPS "Pickup Methods", mapped from the MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD configuration
        // setting.
        //
        $this->pickupMethods = [
            'Daily Pickup' => '01',
            'Customer Counter' => '03',
            'One Time Pickup' => '06',
            'On Call Air Pickup' => '07',
            'Letter Center' => '19',
            'Air Service Center' => '20'
        ];

        // -----
        // UPS "Packaging Types", mapped from the MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE configuration
        // setting.
        //
        $this->packagingTypes = [
            'Unknown' => '00',
            'UPS Letter' => '01',
            'Customer Package' => '02',
            'UPS Tube' => '03',
            'UPS Pak' => '04',
            'UPS Express Box' => '21',
            'UPS 25kg Box' => '24',
            'UPS 10kg Box' => '25'
        ];

        // -----
        // Human-readable Service Code lookup table. The values returned by the Rates and Service "shop" method are numeric.
        // Using these codes, and the administratively defined Origin, the proper human-readable service name is returned.
        //
        // Notes:
        // 1) The origin specified in the admin configuration affects only the product name as displayed to the user.
        // 2) These code-to-name correlations were last verified with the "UPS Rating Package RESTful Developer Guide" dated 2023-02-17.
        //
        $this->serviceCodes = [
            // US Origin
            'US Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_02,
                '03' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_03,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_11,
                '12' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_12,
                '13' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_13,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_54,
                '59' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_59,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_US_ORIGIN_65
            ],
            // Canada Origin
            'Canada Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_02,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_11,
                '12' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_12,
                '13' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_13,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_CA_ORIGIN_65
            ],
            // European Union Origin
            'European Union Origin' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_EU_ORIGIN_65
            ],
            // Puerto Rico Origin
            'Puerto Rico Origin' => [
                '01' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_01,
                '02' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_02,
                '03' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_03,
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_08,
                '14' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_14,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_PR_ORIGIN_65
            ],
            // Mexico Origin
            'Mexico Origin' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_MX_ORIGIN_65
            ],
            // All other origins
            'All other origins' => [
                '07' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_07,
                '08' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_08,
                '11' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_11,
                '54' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_54,
                '65' => MODULE_SHIPPING_UPSOAUTH_SC_OTHER_ORIGIN_65
            ],
        ];
    }

    public function setCurrencyCode($currency_code)
    {
        $this->currencyCode = $currency_code;
    }

    public function getUpsEndpoint()
    {
        return $this->endpoint;
    }

    // -----
    // Retrieves an OAuth token from UPS to use in follow-on requests, returning that value to
    // the caller.
    //
    public function getOAuthToken($client_id, $client_secret)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'x-merchant-id: ' . $client_id,
                'Authorization: Basic ' . base64_encode($client_id . ':' . $client_secret)
            ],
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_URL => $this->endpoint . self::API_OAUTH_TOKEN,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($ch);
        $token = false;
        if ($response === false) {
            $this->debugLog('CURL error requesting Token (' . curl_errno($ch) . ', ' . curl_error($ch) . ')');
        } else {
            $token = json_decode($response);
         }

        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        return $token;
    }

    // -----
    // Retrieve the requested UPS quotes.  This method will return either a JSON-decoded
    // object that represents the received quote information or (bool)false if an error, either
    // CURL or UPS, is indicated.
    //
    public function getAllUpsQuotes($oauth_token)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $oauth_token,
                'Content-Type: application/json',
                'transId: string',
                'transactionSrc: testing',
            ],
            CURLOPT_POSTFIELDS => $this->buildRateRequest(),
            //  CURLOPT_URL => "https://wwwcie.ups.com/api/rating/" . $version . "/" . $requestoption . "?" . http_build_query($query),
            CURLOPT_URL => $this->endpoint . self::API_RATING,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $this->debugLog('CURL error requesting Rates (' . curl_errno($ch) . ', ' . curl_error($ch) . ')');
            $response_details = false;
        } else {
            $response_details = json_decode($response);
            $this->debugLog(json_encode($response_details, JSON_PRETTY_PRINT), true);
            if (!isset($response_details->response->errors) && (!isset($response_details->RateResponse->RatedShipment) || !is_array($response_details->RateResponse->RatedShipment))) {
                $response_details = false;
            }
        }
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        return $response_details;
    }

    // -----
    // This function builds an array containing the to-be-issued Rate Request, returning
    // that array in a JSON-encoded format.
    //
    protected function buildRateRequest()
    {
        global $order, $shipping_num_boxes, $shipping_weight;

        // -----
        // UPS shipping addresses in Puerto Rico require a country-code of 'PR'
        // along with a state-code of 'PR'. If an address is submitted with a
        // country-code of 'US', change that for the shipping quote request.
        //
        $state_province_code = zen_get_zone_code((int)$order->delivery['country']['id'], (int)$order->delivery['zone_id'], '');
        $country_code = $order->delivery['country']['iso_code_2'];
        if ($state_province_code === 'PR' && $country_code === 'US') {
            $country_code = 'PR';
        }

        $rate_request = [
            'RateRequest' => [
                'Request' => [
                    'SubVersion' => self::API_RATING_SUBVERSION,
                    'TransactionReference' => [
                        'CustomerContext' => 'CustomerContext',
                        'TransactionIdentifier' => 'TransactionIdentifier'
                    ],
                ],
                'PickupType' => [
                    'Code' => $this->pickupMethods[$this->getPickupMethod()],
                ],
                'CustomerClassification' => [
                    'Code' => $this->getCustomerClassificationCode(),
                ],
                'Shipment' => [
                    'Shipper' => [
                        'Address' => $this->getOriginShippingAddress(),
                    ],
                    // -----
                    // When rates are requested from the shipping-estimator, the city isn't set and the postcode might not be.  Provide
                    // defaults for the request.
                    //
                    'ShipTo' => [
                        'Address' => [
                            'City' => (!empty($order->delivery['city'])) ? $order->delivery['city'] : '',
                            'StateProvinceCode' => $state_province_code,
                            'PostalCode' => (!empty($order->delivery['postcode'])) ? $order->delivery['postcode'] : '',
                            'CountryCode' => $country_code,
                        ]
                    ],
                   'DeliveryTimeInformation' => [
                        'PackageBillType' => '03',  //- '02': Document only, '03': Non-Document
                    ],
                ],
            ],
        ];

        // -----
        // Include the ResidentialAddressIndicator, if so (er) indicated.
        //
        if ((bool)$this->isResidentialAddress() === true) {
            $rate_request['RateRequest']['Shipment']['ShipTo']['Address']['ResidentialAddressIndicator'] = 'Y';
        }

        $shipper_number = $this->getShipperNumber();
        if ($shipper_number !== '') {
            $rate_request['RateRequest']['Shipment']['Shipper']['ShipperNumber'] = $shipper_number;
            $rate_request['RateRequest']['Shipment']['PaymentDetails']['ShipmentCharge']['Type'] = '01';    //- Transportation
            $rate_request['RateRequest']['Shipment']['PaymentDetails']['ShipmentCharge']['BillShipper']['AccountNumber'] = $shipper_number;
            $rate_request['RateRequest']['Shipment']['ShipmentRatingOptions']['NegotiatedRatesIndicator'] = 'Y';
        }

        // -----
        // Determine the overall package value; it's required for international
        // shipments and does no harm on domestic.  First calculate a per-package
        // value, based on the overall value divided by the number of boxes.
        //
        // These values are sent in the API as whole currency values (no decimal
        // digits).
        //
        $shipment_value = 0;
        if (isset($order->info['subtotal'])) {
            $shipment_value = ceil($order->info['subtotal']);
        } elseif (isset($_SESSION['cart']->total)) {
            $shipment_value = ceil($_SESSION['cart']->total);
        }

        $package_value = number_format(ceil($shipment_value / $shipping_num_boxes), 0, '.', '');
        $shipment_value = $package_value * $shipping_num_boxes;
        $rate_request['RateRequest']['Shipment']['InvoiceLineTotal'] = [
            'CurrencyCode' => $this->currencyCode,
            'MonetaryValue' => (string)$shipment_value,
        ];

        // -----
        // Add the shipment's overall weight; required for international shipments!
        //
        $rate_request['RateRequest']['Shipment']['ShipmentTotalWeight'] = [
           'UnitOfMeasurement' => [
                'Code' => $this->getWeightUnit(),
            ],
            'Weight' => number_format($shipping_weight * $shipping_num_boxes, 5, '.', ''),
        ];

        // -----
        // Build the 'base' Package information.  It's the same for each of the shipping boxes.
        //
        $package_info = [
            'PackagingType' => [
                'Code' => $this->packagingTypes[$this->getPackageType()],
            ],
            'PackageWeight' => [
                'UnitOfMeasurement' => [
                    'Code' => $this->getWeightUnit(),
                ],
                'Weight' => number_format($shipping_weight, 5, '.', ''),
            ],
        ];
        if ($this->packagesAreInsured() === true) {
            $package_info['PackageServiceOptions'] = [
                'DeclaredValue' => [
                    'CurrencyCode' => $this->currencyCode,
                    'MonetaryValue' => $package_value,
                ],
            ];
        }

        // -----
        // Now, add the package(s) to the request (one for each shipping-box).
        //
        $rate_request['RateRequest']['Shipment']['Package'] = [];
        for ($i = 0; $i < $shipping_num_boxes; $i++) {
            $rate_request['RateRequest']['Shipment']['Package'][] = $package_info;
        }

        // -----
        // Give a watching observer the opportunity to make changes to the request, prior to its JSON-encoding.
        //
        $this->notify('NOTIFY_SHIPPING_UPSOAUTH_RATE_REQUEST', $order, $rate_request);

        // -----
        // Write the to-be-issued request for debug.
        //
        $this->debugLog('RAW Rate Request' . "\n" . json_encode($rate_request, JSON_PRETTY_PRINT), true);

        return json_encode($rate_request);
    }
    protected function isResidentialAddress()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_QUOTE_TYPE') === 'Residential';
    }

    // -----
    // From *all* UPS quotes returned, grab only those that the store owner is interested in.  Returns
    // an array of the 'interesting' quotes or (bool)false if none of the returned quotes were
    // 'interesting'.
    //
    public function getConfiguredUpsQuotes($all_ups_quotes)
    {
        $quotes = [];
        $ups_service_types = $this->getServiceTypes();
        $ups_shipping_origin = $this->getShippingOrigin();
        foreach ($all_ups_quotes->RateResponse->RatedShipment as $next_shipment) {
            $service_code = $next_shipment->Service->Code;
            if (strpos($ups_service_types, "[$service_code]") === false) {
                continue;
            }
            $quotes[$service_code] = [
                'cost' => $this->getShipmentCost($next_shipment),
                'business_days_in_transit' => $this->getDaysInTransit($next_shipment),
                'title' => $this->serviceCodes[$ups_shipping_origin][$service_code],
            ];
        }

        $this->debugLog('getConfiguredUpsQuotes, returning: ' . "\n" . var_export($quotes, true));
        return (count($quotes) === 0) ? false : $quotes;
    }

    protected function getHandlingFee($service_code)
    {
        $handling_fee = trim($this->zenConfig("MODULE_SHIPPING_UPSOAUTH_HANDLING_FEE_$service_code", '0'));
        return ($handling_fee === '') ? '0' : $handling_fee;
    }
    protected function getTransitWeightDisplayOptions()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_OPTIONS');
    }
    public function getShippingMethodsFromQuotes($method, $ups_quotes)
    {
        // -----
        // Create the array that maps the UPS service codes to their names.
        //
        $methods = [];
        $handling_fee_multiplier = $this->getFixedHandlingFeeMultiplier();
        foreach ($ups_quotes as $service_code => $quote_info) {
            // -----
            // Any handling-fee can be represented as either a fixed or a percentage.  Determine which
            // and set the fee's adder/multiplier value for the current shipping method.
            //
            // Note that no checking of malformed values is performed; PHP Warnings and Notices will be
            // issued if the value's not numeric or a percentage value doesn't end in %.
            //
            if (strpos($this->getHandlingFee($service_code), '%') === false) {
                $handling_fee_adder = $this->getHandlingFee($service_code) * $handling_fee_multiplier;
                $handling_fee_multiplier = 1;
            } else {
                $handling_fee_adder = 0;
                $handling_fee_multiplier = 1 + (rtrim($this->getHandlingFee($service_code), '%') / 100);
            }

            $type = $quote_info['title'];
            $cost = $quote_info['cost'];
            if ($method === '' || $method === $type) {
                $methods[] = $this->getCurrentMethodQuote($quote_info, $method, $type, $cost, $handling_fee_multiplier, $handling_fee_adder);
            }
        }
        return $methods;
    }
    protected function getFixedHandlingFeeMultiplier()
    {
        global $shipping_num_boxes;
        return ($this->zenConfig('MODULE_SHIPPING_UPSOAUTH_HANDLING_APPLIES') === 'Box') ? $shipping_num_boxes : 1;
    }
//    protected function getCurrentMethodQuote(array $quote_info, string $method, string $type, string $cost, $handling_fee_multiplier, $handling_fee_adder)
    protected function getCurrentMethodQuote(array $quote_info, $method, $type, $cost, $handling_fee_multiplier, $handling_fee_adder)
    {
        $title = $type;
        if (strpos($this->getTransitWeightDisplayOptions(), 'transit') !== false && $quote_info['business_days_in_transit'] !== false) {
            $title .= ' ' . sprintf(MODULE_SHIPPING_UPSOAUTH_ETA_TEXT, (int)$quote_info['business_days_in_transit']);
        }

        return [
            'id' => $type,
            'title' => $title,
            'cost' => ($handling_fee_multiplier * $cost) + $handling_fee_adder,
        ];

    }

    protected function getUnitWeight()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT');
    }
    public function getWeightInfo()
    {
        global $shipping_num_boxes, $shipping_weight;

        $weight_info = '';
        if ((strpos($this->getTransitWeightDisplayOptions(), 'weight') !== false)) {
            $weight_info = ' (' . $shipping_num_boxes . ($shipping_num_boxes > 1 ? ' pkg(s) x ' : ' pkg x ') . number_format($shipping_weight, 2) . ' ' . strtolower($this->getUnitWeight()) . ' total)';
        }
        return $weight_info;
    }

    // -----
    // "Helper" methods to enable an extended class to provide values different than those
    // configured for the 'base' upsoauth shipping module.
    //
    protected function getPickupMethod()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_PICKUP_METHOD');
    }
    protected function getCustomerClassificationCode()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_CUSTOMER_CLASSIFICATION_CODE');
    }
    protected function getOriginShippingAddress()
    {
        return [
            'City' => $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_ORIGIN_CITY'),
            'StateProvinceCode' => $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_ORIGIN_STATEPROV'),
            'PostalCode' => $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_ORIGIN_POSTALCODE'),
            'CountryCode' => $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_ORIGIN_COUNTRY'),
        ];
    }
    protected function getPackageType()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_PACKAGE_TYPE');
    }
    protected function getShipperNumber()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_SHIPPER_NUMBER');
    }
    protected function packagesAreInsured()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_INSURE') === 'True';
    }
    protected function getWeightUnit()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_UNIT_WEIGHT');
    }
    protected function getServiceTypes()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_TYPES');
    }
    protected function getShippingOrigin()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_ORIGIN');
    }
    protected function getShippingDaysDelay()
    {
        return $this->zenConfig('MODULE_SHIPPING_UPSOAUTH_SHIPPING_DAYS_DELAY');
    }
    protected function getDaysInTransit($next_shipment)
    {
        $days_in_transit = isset($next_shipment->GuaranteedDelivery->BusinessDaysInTransit) ? $next_shipment->GuaranteedDelivery->BusinessDaysInTransit : false;
        if ($days_in_transit !== false) {
            $days_in_transit += ceil((float)$this->getShippingDaysDelay());
        }
        return $days_in_transit;
    }
    protected function getShipmentCost($next_shipment)
    {
        if (isset($next_shipment->NegotiatedRateCharges->TotalCharge->MonetaryValue)) {
            $cost = $next_shipment->NegotiatedRateCharges->TotalCharge->MonetaryValue;
        } else {
            $cost = $next_shipment->TotalCharges->MonetaryValue;
        }
        return $cost;
    }

    // -----
    // Uses, if present, or emulates otherwise the zc300+ "zen_config"
    // function.
    //
    // @since upsoauth v1.4.0
    //
    private function zenConfig($config_key, $default_value = null)
    {
        static $zen_config_present;
        if (!isset($zen_config_present)) {
            $zen_config_present = function_exists('zen_config');
        }

        if ($zen_config_present) {
            return zen_config($config_key, $default_value);
        }

        if (defined($config_key)) {
            return constant($config_key);
        }

        return ($default_value !== null) ? $default_value : null;
    }

    protected function debugLog($message, $include_spacer = false)
    {
        if ($this->debug === true) {
            $spacer = ($include_spacer === false) ? '' : "------------------------------------------\n";
            error_log($spacer . date('Y-m-d H:i:s') . ': ' . $message . "\n", 3, $this->logfile);
        }
    }
}
