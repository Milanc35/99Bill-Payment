<?php
namespace Payment99Bill;

use Payment99Bill\Request\PayRequest;
use Payment99Bill\Exception\ExceptionConcern;

/**
 * Payment service interface Supports Below APIs for 99bill payment Service
 *    1. Payment Form
 *    2. Pay Complete Response
 *    3. Return Response 
 *    4. Payment Query
 *    5. Refund 
 *    6. Refund Query
 * Reference : https://www.99bill.com
 */

final class Gateway 
{
    private $config;

    public function __construct(GatewayConfigConcern $config)
    {
        $this->config = $config;
    }

    /**
     * Submit payment data to 99bill website.
     *
     * @param  array $request
     * @throws ExceptionConcern
     * @return string
     */
    public function pay(array $request)
    {
        return (new PayRequest($this->config, $request))->send();
    }

    /**
     * Verify params sign.
     *
     * @param  array $params
     * @return bool
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 12:32 PM
     */
    protected function signResponse(array $params)
    {
        $sortedParams = [
            'merchantAcctId' => $this->getMerchantId() . ($this->isRmbAccount() ? '01' : ''),
            'version'        => isset($params['version']) ? $params['version'] : '',
            'language'       => isset($params['language']) ? $params['language'] : '',
            'signType'       => isset($params['signType']) ? $params['signType'] : '',
            'payType'        => isset($params['payType']) ? $params['payType'] : '',
            'bankId'         => isset($params['bankId']) ? $params['bankId'] : '',
            'orderId'        => isset($params['orderId']) ? $params['orderId'] : '',
            'orderTime'      => isset($params['orderTime']) ? $params['orderTime'] : '',
            'orderAmount'    => isset($params['orderAmount']) ? $params['orderAmount'] : '',
            'bindCard'       => isset($params['bindCard']) ? $params['bindCard'] : '',
            'bindMobile'     => isset($params['bindMobile']) ? $params['bindMobile'] : '',
            'dealId'         => isset($params['dealId']) ? $params['dealId'] : '',
            'bankDealId'     => isset($params['bankDealId']) ? $params['bankDealId'] : '',
            'dealTime'       => isset($params['dealTime']) ? $params['dealTime'] : '',
            'payAmount'      => isset($params['payAmount']) ? $params['payAmount'] : '',
            'fee'            => isset($params['fee']) ? $params['fee'] : '',
            'ext1'           => isset($params['ext1']) ? $params['ext1'] : '',
            'ext2'           => isset($params['ext2']) ? $params['ext2'] : '',
            'payResult'      => isset($params['payResult']) ? $params['payResult'] : '',
            'errCode'        => isset($params['errCode']) ? $params['errCode'] : '',
        ];

        $signMsg = isset($params['signMsg']) ? base64_decode($params['signMsg']) : null;
        $publicKeySource   = openssl_get_publickey($this->getPublickey());
        $verifyRes = openssl_verify($this->getEncodeContent($sortedParams, true), $signMsg, $publicKeySource , OPENSSL_ALGO_SHA1);
        openssl_free_key($publicKeySource);

        return $verifyRes == 1;
    }

    /**
     * Process pay complete request.
     *
     * @param  array $notifyReuestedParams
     * @return void
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 12:53 PM
     */
    public function payComplete(array $notifyReuestedParams)
    {
        $this->success = false;
        try {
            $match = $this->signResponse($notifyReuestedParams);
            $notifyReuestedParams['paid'] = false;
            if ($match) {
                if (isset($notifyReuestedParams['payResult']) && $notifyReuestedParams['payResult'] == '10') {
                    $notifyReuestedParams['paid'] = true;
                }

                $this->success  = true;
                $this->response = $notifyReuestedParams;
            } else {
                $this->errorResponse["code"]    = 402;
                $this->errorResponse["message"] = "Sign doesn't verified.";
            }
        } catch (\Exception $e) {
            $this->errorResponse["code"]    = $e->getCode();
            $this->errorResponse["message"] = $e->getMessage();
        }
    }

    /**
     * Query payment/transaction.
     * Tip: contain multiple rows.
     *
     * @param  array $params
     * @return void
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 03:52 PM
     */
    public function paymentQuery(array $params)
    {
        try {
            $searchParams = [
                'inputCharset'   => isset($params['inputCharset']) ? $params['inputCharset'] : self::ENCODING,
                'version'        => isset($params['version']) ? $params['version'] : self::VERSION,
                'signType'       => 1,
                'merchantAcctId' => $this->getMerchantId() . ($this->isRmbAccount() ? '01' : ''),
                'queryType'      => isset($params['queryType']) ? $params['queryType'] : '1',
                'queryMode'      => isset($params['queryMode']) ? $params['queryMode'] : '1',
                'startTime'      => isset($params['startTime']) ? $params['startTime'] : '',
                'endTime'        => isset($params['endTime']) ? $params['endTime'] : '',
                'requestPage'    => (isset($params['requestPage']) && $params['requestPage'] > 0) ? $params['requestPage'] : '1',
                'orderId'        => isset($params['orderId']) ? $params['orderId'] : '',
            ];

            $singStr                        = $this->getEncodeContent($searchParams, true) . '&key=' . $this->getQueryKey();
            $searchParams['signMsg']        = strtoupper(md5($singStr));
            $sourceResult                   = $this->soapRequest($this->getEndPoint('QUERY'), 'gatewayOrderQuery', $searchParams);
            $this->success                  = false;
            $this->errorResponse["code"]    = 304;
            $this->errorResponse["message"] = 'Failed to get response from API.';
            if ($sourceResult !== false) {
                $result = (array) $sourceResult;
                $errorCode = $result['errCode'];
                if (!$errorCode && isset($result['orders']) && is_array($result['orders'])) {
                    foreach ($result['orders'] as &$orderInfo) {
                        $orderInfo = (array)$orderInfo;
                    }

                    $this->success  = true;
                    $this->response = $result['orders'];
                } else {
                    $this->errorResponse["code"]    = $errorCode;
                    $this->errorResponse["message"] = isset($this->error[$errorCode]) ? $this->error[$errorCode] : $this->error['0000'];
                }
            }
        } catch (\Exception $e) {
            $this->success                  = false;
            $this->errorResponse["code"]    = $e->getCode();
            $this->errorResponse["message"] = $e->getMessage();
        }
    }

    /**
     * Process refund payment.
     *
     * @param  array $params
     * @return void
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 04:21 PM
     */
    public function refund(array $params)
    {
        try {
            $validateParams = ['amount', 'orderId'];
            if (isset($params['refundDate'])) {
                $validateParams[] = 'refundDate';
            }
            if (isset($params['refundReference'])) {
                $validateParams[] = 'refundReference';
            }

            $this->validateParams($params, $validateParams);
            $refundParams = [
                'merchant_id'  => $this->getMerchantId(),
                'version'      => 'bill_drawback_api_2',
                'command_type' => '001',
                'orderid'      => $params['orderId'],
                'amount'       => floatval($params['amount']),
                'postdate'     => isset($params['refundDate']) ? $params['refundDate'] : date("YmdHis"),
                'txOrder'      => isset($params['refundReference']) ? $params['refundReference'] : date("YmdHis"),
            ];
            $singStr             = $this->getEncodeContent($refundParams, true) . '&merchant_key=' . $this->getRefundKey();
            $refundParams['mac'] = strtoupper(md5(str_replace("&", '', $singStr)));
            $response            = $this->httpGetRequest($this->getEndPoint('REFUND'), $refundParams);
            // default response
            $this->success                  = false;
            $this->errorResponse["code"]    = 304;
            $this->errorResponse["message"] = 'Failed to get response from API.';
            if ($response) {
                $response = $this->flatXml2array($response);
            }
            if ($response) {
                if ($response['RESULT'] == 'Y') {
                    $this->success  = true;
                    $this->response = [
                        'orderId'         => isset($response['ORDERID']) ? $response['ORDERID'] : null,
                        'refundReference' => isset($response['TXORDER']) ? $response['TXORDER'] : null,//Refund Reference.
                        'amount'          => isset($response['AMOUNT']) ? $response['AMOUNT'] : 0,
                    ];
                } else {
                    $this->errorResponse["code"]    = 500;
                    $this->errorResponse["message"] = $response['CODE'];
                }
            }
        } catch (\Exception $e) {
            $this->success                  = false;
            $this->errorResponse["code"]    = $e->getCode();
            $this->errorResponse["message"] = $e->getMessage();
        }
    }

    /**
     * Query refund/drawback.
     *
     * @param  array $params
     * @return void
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 04:21 PM
     */
    public function refundQuery(array $params)
    {
        try {
            $searchParams = [
                'version'             => isset($params['version']) ? $params['version'] : self::VERSION,
                'signType'            => 1,
                'merchantAcctId'      => $this->getMerchantId() . ($this->isRmbAccount() ? '01' : ''),
                'startDate'           => isset($params['startDate']) ? $params['startDate'] : '',
                'endDate'             => isset($params['endDate']) ? $params['endDate'] : '',
                'lastUpdateStartDate' => isset($params['lastUpdateStartDate']) ? $params['lastUpdateStartDate'] : '',
                'lastUpdateEndDate'   => isset($params['lastUpdateEndDate']) ? $params['lastUpdateEndDate'] : '',
                'customerBatchId'     => isset($params['customerBatchId']) ? $params['customerBatchId'] : '',
                'orderId'             => isset($params['orderId']) ? $params['orderId'] : '',
                'requestPage'         => (isset($params['requestPage']) && $params['requestPage'] > 0) ? $params['requestPage'] : '1',
                'rOrderId'            => isset($params['rOrderId']) ? $params['rOrderId'] : '',
                'seqId'               => isset($params['seqId']) ? $params['seqId'] : '',
                'extra_output_column' => isset($params['extra_output_column']) ? $params['extra_output_column'] : '',
                'status'              => isset($params['status']) ? $params['status'] : '',
            ];

            $singStr                        = $this->getEncodeContent($searchParams, true) . '&key=' . $this->getQueryKey();
            $searchParams['signMsg']        = strtoupper(md5($singStr));
            $sourceResult                   = $this->soapRequest($this->getEndPoint('QUERY_REFUND'), 'query', $searchParams);
            $this->success                  = false;
            $this->errorResponse["code"]    = 304;
            $this->errorResponse["message"] = 'Failed to get response from API.';
            if ($sourceResult !== false) {
                $result    = (array) $sourceResult;
                $errorCode = $result['errCode'];
                if (!$errorCode && isset($result['results']) && is_array($result['results'])) {
                    foreach ($result['results'] as &$refundInfo) {
                        $refundInfo = (array)$refundInfo;
                    }

                    $this->success  = true;
                    $this->response = $result['results'];
                } else {
                    $this->errorResponse["code"]    = $errorCode;
                    $this->errorResponse["message"] = isset($this->error[$errorCode]) ? $this->error[$errorCode] : $this->error['00000'];
                }
            }
        } catch (\Exception $e) {
            $this->success                  = false;
            $this->errorResponse["code"]    = $e->getCode();
            $this->errorResponse["message"] = $e->getMessage();
        }
    }

    /**
     * Async result or notify string.
     *
     * @param  array $params
     * @return void
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-08 01:40 PM
     */
    public function finishNotify($result, $redirectUrl)
    {
        if ($result) {
            return '<result>1</result><redirecturl>' . $redirectUrl . '</redirecturl>';
        } else {
            return '<result>0</result><redirecturl>' . $redirectUrl . '</redirecturl>';
        }
    }

    /**
     * SOAP interface(Request) utility.
     *
     * @param  string $url
     * @param  string $functionName
     * @param  array  $params
     * @return mixed
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-07 06:30 PM
     */
    public function soapRequest($url, $functionName, array $params)
    {
        if (!$url || !$functionName) {
            return false;
        }

        $soapclientObj = new \SoapClient($url);
        try {
            $result = $soapclientObj->__soapCall($functionName, [$params]);

            return $result;
        } catch (\SOAPFault $e) {
            throw new \Exception('[SOAPFault]' . $e->getMessage(), 500);
        }

        return false;
    }

    /**
     * HTTP[GET] interface(request) utility.
     *
     * @param  string $url
     * @param  array  $params
     * @return mixed
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-07 06:55 PM
     */
    public function httpGetRequest($url, $params)
    {
        if (!$url) {
            return false;
        }
        $url     = $url . '?' . http_build_query($params);
        $curlObj = curl_init($url);
        curl_setopt($curlObj, CURLOPT_HTTPGET, true);
        curl_setopt($curlObj, CURLOPT_HEADER, 0);
        curl_setopt($curlObj, CURLOPT_TIMEOUT, 60);
        curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curlObj, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curlObj, CURLOPT_SSLVERSION, 6);
        $response = curl_exec($curlObj);
        curl_close($curlObj);

        return $response;
    }

    /**
     * XML to flat array utility.
     *
     * @param  string $url
     * @param  array  $params
     * @return mixed
     *
     * @author Milan Chhaniyara <milanc.bipl@gmail.com>
     * @date   2019-06-10 05:30 PM
     */
    public function flatXml2array($content)
    {
        $p;
        $responseToArr = [];
        try {
            $p = xml_parser_create();
            xml_parse_into_struct($p, $content, $value, $index);
            xml_parser_free($p);
            $responseToArr = array_column($value, 'value', 'tag');
        } catch (\Exception $e) {
        }

        return $responseToArr;
    }
}
