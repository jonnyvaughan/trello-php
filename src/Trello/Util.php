<?php
/**
 * Trello Utility methods
 * PHP version 5
 *
 * @copyright  2014 Steven Maguire
 */

class Trello_Util
{
    /**
     * extracts an attribute and returns an array of objects
     *
     * extracts the requested element from an array, and converts the contents
     * of its child arrays to objects of type Trello_$attributeName, or returns
     * an array with a single element containing the value of that array element
     *
     * @access public
     * @param array $attribArray attributes from a search response
     * @param string $attributeName indicates which element of the passed array to extract
     *
     * @return array array of Trello_$attributeName objects, or a single element array
     */
    public static function extractAttributeAsArray(&$attribArray, $attributeName)
    {
        if (!isset($attribArray[$attributeName])) {
            return [];
        }

        $data = $attribArray[$attributeName];

        $classFactory = self::buildClassName($attributeName) . '::factory';

        if (is_array($data)) {
            $objectArray = array_map($classFactory, $data);
        } else {
            return [$data];
        }

        unset($attribArray[$attributeName]);

        return $objectArray;
    }

    /**
     * Throws an exception based on the type of error
     *
     * @access public
     * @param string $statusCode    HTTP status code to throw exception from
     * @param string $message       Optional message
     *
     * @throws Trello_Exception     multiple types depending on the error
     */
    public static function throwStatusCodeException($statusCode, $message = null)
    {
        switch($statusCode) {
            case 401:
                throw new Trello_Exception_Authentication($message);
                break;
            case 403:
                throw new Trello_Exception_Authorization($message);
                break;
            case 404:
                throw new Trello_Exception_NotFound($message);
                break;
            case 426:
                throw new Trello_Exception_UpgradeRequired($message);
                break;
            case 500:
                throw new Trello_Exception_ServerError($message);
                break;
            case 503:
                throw new Trello_Exception_DownForMaintenance($message);
                break;
            default:
                throw new Trello_Exception_Unexpected('Unexpected HTTP_RESPONSE #'.$statusCode);
                break;
        }
    }

    /**
     * Removes the Trello_ header from a classname
     *
     * @access public
     * @param string $name Trello_ClassName
     *
     * @return string camelCased classname minus Trello_ header
     */
    public static function cleanClassName($name)
    {
        $classNamesToResponseKeys = [
            'Action' => 'action',
            'Board' => 'board',
            'Card' => 'card',
            'CheckList' => 'checkList',
            'List' => 'list',
            'Member' => 'member',
            'Notification' => 'notification',
            'Organization' => 'organization',
            'Session' => 'session',
            'Token' => 'token',
            'Type' => 'type',
            'Webhook' => 'webhook'
        ];

        $name = str_replace('Trello_', '', $name);
        return $classNamesToResponseKeys[$name];
    }

    /**
     * Addes Trello_ header to classname
     *
     * @access public
     * @param string $name className
     *
     * @return string Trello_ClassName
     */
    public static function buildClassName($name)
    {
        $responseKeysToClassNames = [
            'creditCard' => 'CreditCard',
            'customer' => 'Customer',
            'subscription' => 'Subscription',
            'transaction' => 'Transaction',
            'verification' => 'CreditCardVerification',
            'addOn' => 'AddOn',
            'discount' => 'Discount',
            'plan' => 'Plan',
            'address' => 'Address',
            'settlementBatchSummary' => 'SettlementBatchSummary',
            'merchantAccount' => 'MerchantAccount'
        ];

        return 'Trello_' . $responseKeysToClassNames[$name];
    }

    /**
     * convert alpha-beta-gamma to alphaBetaGamma
     *
     * @access public
     * @param string $string
     *
     * @return string modified string
     */
    public static function delimiterToCamelCase($string, $delimiter = '[\-\_]')
    {
        // php doesn't garbage collect functions created by create_function()
        // so use a static variable to avoid adding a new function to memory
        // every time this function is called.
        static $callback = null;
        if ($callback === null) {
            $callback = create_function('$matches', 'return strtoupper($matches[1]);');
        }

        return preg_replace_callback('/' . $delimiter . '(\w)/', $callback, $string);
    }

    /**
     * convert alpha-beta-gamma to alpha_beta_gamma
     *
     * @access public
     * @param string $string
     *
     * @return string modified string
     */
    public static function delimiterToUnderscore($string)
    {
        return preg_replace('/-/', '_', $string);
    }


    /**
     * find capitals and convert to delimiter + lowercase
     *
     * @access public
     * @param string $string
     *
     * @return string modified string
     */
    public static function camelCaseToDelimiter($string, $delimiter = '-')
    {
        // php doesn't garbage collect functions created by create_function()
        // so use a static variable to avoid adding a new function to memory
        // every time this function is called.
        static $callbacks = [];
        if (!isset($callbacks[$delimiter])) {
            $callbacks[$delimiter] = create_function('$matches', "return '$delimiter' . strtolower(\$matches[1]);");
        }

        return preg_replace_callback('/([A-Z])/', $callbacks[$delimiter], $string);
    }

    /**
     * Convert associative array to string
     *
     * @access public
     * @param array $array associative array to implode
     * @param string $separator (optional, defaults to =)
     * @param string $glue (optional, defaults to ', ')
     *
     * @return string Imploded array
     */
    public static function implodeAssociativeArray($array, $separator = '=', $glue = ', ')
    {
        // build a new array with joined keys and values
        $tmpArray = null;
        foreach ($array AS $key => $value) {
            $tmpArray[] = $key . $separator . $value;
        }
        // implode and return the new array
        return (is_array($tmpArray)) ? implode($glue, $tmpArray) : false;
    }

    /**
     * Convert attributes to string
     *
     * @access public
     * @param  array $attributes Attributes to convert
     *
     * @return string Converted string
     */
    public static function attributesToString($attributes) {
        $printableAttribs = [];
        foreach ($attributes AS $key => $value) {
            if (is_array($value)) {
                $pAttrib = Trello_Util::attributesToString($value);
            } elseif ($value instanceof DateTime) {
                $pAttrib = $value->format(DateTime::RFC850);
            } else {
                $pAttrib = $value;
            }
            $printableAttribs[$key] = sprintf('%s', $pAttrib);
        }
        return Trello_Util::implodeAssociativeArray($printableAttribs);
    }

    /**
     * verify user request structure
     *
     * compares the expected signature of a gateway request
     * against the actual structure sent by the user
     *
     * @access public
     * @param array $signature
     * @param array $attributes
     *
     * @throws InvalidArgumentException
     */
    public static function verifyKeys($signature, $attributes)
    {
        $validKeys = self::_flattenArray($signature);
        $userKeys = self::_flattenUserKeys($attributes);
        $invalidKeys = array_diff($userKeys, $validKeys);
        $invalidKeys = self::_removeWildcardKeys($validKeys, $invalidKeys);

        if(!empty($invalidKeys)) {
            asort($invalidKeys);
            $sortedList = join(', ', $invalidKeys);
            throw new InvalidArgumentException('invalid keys: '. $sortedList);
        }
    }

    /**
     * Build quesry string from array
     *
     * @access public
     * @param  array Array to convert
     *
     * @return string Query string
     */
    public static function makeQueryStringFromArray($array = [])
    {
        return http_build_query($array);
    }

    /**
     * flattens a numerically indexed nested array to a single level
     *
     * @access private
     * @param array $keys
     * @param string $namespace
     *
     * @return array
     */
    private static function _flattenArray($keys, $namespace = null)
    {
        $flattenedArray = [];
        foreach($keys AS $key) {
            if(is_array($key)) {
                $theKeys = array_keys($key);
                $theValues = array_values($key);
                $scope = $theKeys[0];
                $fullKey = empty($namespace) ? $scope : $namespace . '[' . $scope . ']';
                $flattenedArray = array_merge($flattenedArray, self::_flattenArray($theValues[0], $fullKey));
            } else {
                $fullKey = empty($namespace) ? $key : $namespace . '[' . $key . ']';
                $flattenedArray[] = $fullKey;
            }
        }
        sort($flattenedArray);
        return $flattenedArray;
    }

    /**
     * Flatten user keys
     *
     * @access private
     * @param  array $keys Keys to flatten
     * @param  string $namespace Optional namespace
     *
     * @return array Flattened array of Keys
     */
    private static function _flattenUserKeys($keys, $namespace = null)
    {
       $flattenedArray = [];

       foreach($keys AS $key => $value) {
           $fullKey = empty($namespace) ? $key : $namespace;
           if (!is_numeric($key) && $namespace != null) {
              $fullKey .= '[' . $key . ']';
           }
           if (is_numeric($key) && is_string($value)) {
              $fullKey .= '[' . $value . ']';
           }
           if(is_array($value)) {
               $more = self::_flattenUserKeys($value, $fullKey);
               $flattenedArray = array_merge($flattenedArray, $more);
           } else {
               $flattenedArray[] = $fullKey;
           }
       }
       sort($flattenedArray);
       return $flattenedArray;
    }

    /**
     * removes wildcard entries from the invalid keys array
     *
     * @access private
     * @param array $validKeys
     * @param array $invalidKeys
     *
     * @return array
     */
    private static function _removeWildcardKeys($validKeys, $invalidKeys)
    {
        foreach($validKeys AS $key) {
            if (stristr($key, '[_anyKey_]')) {
                $wildcardKey = str_replace('[_anyKey_]', '', $key);
                foreach ($invalidKeys AS $index => $invalidKey) {
                    if (stristr($invalidKey, $wildcardKey)) {
                        unset($invalidKeys[$index]);
                    }
                }
            }
        }
        return $invalidKeys;
    }
}
