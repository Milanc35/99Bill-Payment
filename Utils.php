<?php

namespace Payment99Bill;

use Payment99Bill\Exception\RequestSigningException;
use Payment99Bill\Exception\ValidationException;
use Throwable;

class Utils {
    const ERROR_CODES = [
        '00000' => 'Non-Identified Problem',
        '10001' => 'The gateway version is incorrect or does not exist',
        '10002' => 'Signature type is incorrect or does not exist',
        '10003' => 'The CNY account format is incorrect',
        '10004' => 'The query method is incorrect or does not exist',
        '10005' => 'he query mode is incorrect or does not exist',
        '10006' => 'The query start time is incorrect',
        '10007' => 'The query end time is incorrect',
        '10008' => 'The merchant order number is not in the correct format',
        '1000 ' => 'The contact information of the payer is incorrect. Please enter the legal contact address.',
        '10010' => 'Character set input is incorrect',
        '11001' => 'Start time cannot be after the end time',
        '11002' => 'Allow a query for a period of up to 30 days',
        '11003' => 'Signature string does not match',
        '11004' => 'The query end time is later than the current time',
        '20001' => 'The account does not exist or has been logged out',
        '20002' => 'Signature string does not match, you have no right to query',
        '30001' => 'The system is busy, please check again later',
        '30002' => 'The query process is abnormal, please try again later',
        '31001' => 'No transaction record during this time period',
        '31002' => 'No successful transaction record during this time period',
        '31003' => 'The merchant order number does not exist',
        '31004' => 'The query result exceeds the allowable file range',
        '31005' => 'The transaction payment corresponding to the order number was not successful',
        '31006' => 'The current record set page number does not exist',
        '10011' => 'Order date is incorrect, Please enter correct date yyyyMMdd',
        '10012' => 'Order time is incorrect, please enter the time in yyyyMMddhhmmss format',
        '10017' => 'Extended parameter one is incorrect',
        '10018' => 'Extended parameter two is incorrect',
        '10019' => 'The specified payment method is incorrect',
        '10022' => 'Unsupported language type, the language supported by the system is 1. [Chinese], 2. [English]',
        '10023' => 'Unsupported signature type, the system supports a signature type of 1. [MD5]',
        '10024' => 'The merchant has not opened RMB gateway.',
        '10025' => 'The merchant has not opened the international card RMB gateway.',
        '10026' => 'The merchant has not opened a telephone to pay the RMB gateway.',
    ];

    const PAY_TYPE = [
        '10' => 'Online Banking Payment',
        '11' => 'Phone wallet Payment',
        '12' => 'Quick Payment [CNY Account]',
        '13' => 'Offline Payments',
        '14' => 'B2B Payments',
        '21' => 'Quick Payment',
        '00' => 'All Payments',
    ];

    const BANKS = [
        'CMB',  'ICBC', 'ABC', 'CCB', 'BOC', 'SPDB', 'BCOM', 'CMBC', 'SRCB', 'BOB', 'NBCB', 'HSB', 'CZB',
        'PAB',  'GDB', 'CITIC', 'HXB', 'CIB', 'CBHB', 'BJRCB', 'NJCB', 'CEB', 'HZB', 'SHB', 'PSBC',
    ];

    /**
     * Get encoded request string.
     *
     * @param  array  $params
     * @param  bool   $filterEmpty   (default false)
     * @param  bool   $toJson (default false)
     * @return string
     */
    public static function encodeRequest(array $params, bool $filterEmpty = false, bool $toJson = false)
    {
        if ($filterEmpty) {
            $params = array_filter($params, 'strlen');
        }

        if ($toJson) {
            return json_encode($params, JSON_UNESCAPED_UNICODE);
        }

        return urldecode(http_build_query($params));
    }
}
