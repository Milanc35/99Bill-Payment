<?php

namespace Payment99Bill\Request;

use Payment99Bill\Exception\RequestSigningException;
use Payment99Bill\Utils;
use Throwable;

class PayRequest extends AbstractRequest
{
    /**
     * Send request from for payment
     *
     * @return string
     */
    public function send()
    {
        $this->validateParams($this->request, [
            'returnUrl', 
            'orderAmount', 
            'orderId', 
            'productId', 
            'productName',
        ]);

        if (!isset($this->request['ext1'])) {
            $this->request['ext1'] = isset($this->request['productName']) ? $this->request['productName'] : '';
        }

        $finalFormParam = [
            'inputCharset'     => $this->getRequestValue('inputCharset', self::ENCODING),
            'pageUrl'          => $this->getRequestValue('returnUrl'),
            'bgUrl'            => $this->getRequestValue('notifyUrl', ''),
            'version'          => $this->getRequestValue('version', self::VERSION),
            'language'         => $this->getRequestValue('language',  self::LANGUAGE),
            'signType'         => $this->getRequestValue('signType', self::SIGN_TYPE),
            'merchantAcctId'   => $this->config->getMerchantId(). ($this->config->isRmbAccount() ? self::RMB_ACC_CODE : ''),
            'payerName'        => $this->getRequestValue('payerName', ''),
            'payerContactType' => $this->getRequestValue('payerContactType', ''),
            'payerContact'     => $this->getRequestValue('payerContact', ''),
            'payerIdType'      => $this->getRequestValue('payerIdType', ''),
            'payerId'          => $this->getRequestValue('payerId', ''),
            'payerIP'          => $this->getRequestValue('payerIP', ''),
            'orderId'          => $this->getRequestValue('orderId'),
            'orderAmount'      => $this->getRequestValue('orderAmount') * 100,
            'orderTime'        => $this->getRequestValue('orderTime', date('Y-m-d H:i:s')),
            'orderTimestamp'   => $this->getRequestValue('orderTimestamp', date('YmdHis')),
            'productName'      => $this->getRequestValue('productName', ''),
            'productNum'       => $this->getRequestValue('quantity', 1),
            'productId'        => $this->getRequestValue('productId', ''),
            'productDesc'      => $this->getRequestValue('description', ''),
            'ext1'             => $this->getRequestValue('ext1', $this->getRequestValue('productName', '')),
            'ext2'             => $this->getRequestValue('ext2', ''),
            'payType'          => $this->getRequestValue('payType', '00'),
            'bankId'           => $this->getRequestValue('bankId', ''),
            'cardIssuer'       => $this->getRequestValue('cardIssuer', ''),
            'cardNum'          => $this->getRequestValue('cardNum', ''),
            'remitType'        => $this->getRequestValue('remitType', ''),
            'remitCode'        => $this->getRequestValue('remitCode'),
            'redoFlag'         => $this->getRequestValue('redoFlag', '0'),
            'pid'              => $this->getRequestValue('pid', ''),
            'submitType'       => $this->getRequestValue('submitType', ''),
            'orderTimeOut'     => $this->getRequestValue('orderTimeOut', ''),
            'extDataType'      => $this->getRequestValue('extDataType', ''),
            'extDataContent'   => $this->getRequestValue('extDataContent', ''),
        ];
        
        $finalFormParam['signMsg'] = $this->signRequest($finalFormParam);

        $hiddenFields = '';
        foreach ($finalFormParam as $key => $value) {
            $hiddenFields .= sprintf(
                    '<input type="hidden" name="%s" value="%s" />',
                    htmlentities($key, ENT_QUOTES, 'UTF-8', false),
                    htmlentities($value, ENT_QUOTES, 'UTF-8', false)
                ) . "\n";
        }

        $output = '<!DOCTYPE html>
                    <html>
                        <head>
                            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                            <title>Redirecting...</title>
                        </head>
                        <body onload="document.forms[0].submit();">
                            <form action="%s" method="post">
                                %s
                                <input style="display: none;" type="submit" value="Continue" />
                            </form>
                        </body>
                    </html>';

        $payUrl = $this->config->getEndpoint(). "/gateway/recvMerchantInfoAction.htm";
        $output = sprintf(
            $output,
            htmlentities($payUrl, ENT_QUOTES, 'UTF-8', false),
            $hiddenFields
        );

        return $output;
    }

    /**
     * Sign request params
     *
     * @param  array  $params
     * @param  string $privateKey
     * @return string
     */
    protected function signRequest(array $params)
    {
        $keyResource = openssl_get_privatekey($this->config->getPrivatekey());
        $signMsg          = null;
        $message          = "Failed to sign request params";
        try {
            openssl_sign(Utils::encodeRequest($params, true), $signMsg, $keyResource, OPENSSL_ALGO_SHA1);
        } catch (Throwable $e) {
            if ($e->getCode() == 2) {
                $message = $e->getMessage();
            }
        }

        if (PHP_MAJOR_VERSION < 8) {
            openssl_pkey_free($keyResource);
        }

        if (!$signMsg) {
            throw new RequestSigningException($message, 501);
        }

        return base64_encode($signMsg);
    }

}
