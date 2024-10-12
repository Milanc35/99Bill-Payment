<?php

namespace Payment99Bill\Request;

use Payment99Bill\Exception\ValidationException;
use Payment99Bill\GatewayConfigConcern;

abstract class AbstractRequest
{
    const ENCODING     = '1';
    const VERSION      = 'v2.0';
    const LANGUAGE     = '1';
    const SIGN_TYPE    = "4";
    const RMB_ACC_CODE = '01';

    protected $config;
    protected $request;

    public function __construct(GatewayConfigConcern $config, array $request) {
        $this->request = $request;
        $this->config  = $config;
    }

    abstract public function send();

    protected function getRequestValue($key, $default = null)
    {
        if (isset($this->request[$key])) {
            return $this->request[$key];
        }

        return $default;
    }

        /**
     * Validate Process params
     * serial validation process.
     *
     * @param  array $params
     * @param  array $paramKeys
     * @return bool
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 11:05 AM
     */
    protected function validateParams(array $params, array $paramKeys)
    {
        $urlRezEx = "/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i";
        foreach ($paramKeys as $value) {
            if (!isset($params[$value]) || empty($params[$value])) {
                throw new ValidationException("$value is Required.", 402);
            }

            $lValue = strtolower($value);
            if (strpos($lValue, 'url') !== false) {
                if (!preg_match($urlRezEx, $params[$value])) {
                    throw new ValidationException("$value is not valid URL.", 402);
                }
            }

            if (str_replace(['amount', 'price', 'cost'], '', $lValue) != $lValue) {
                if (floatval($params[$value]) != $params[$value]) {
                    throw new ValidationException("$value is not valid.", 402);
                }

                if (floatval($params[$value]) <= 0 ) {
                    throw new ValidationException("$value must be greater then 0", 402);
                }
            }
        }

        return true;
    }
}