<?php

namespace Payment99Bill;

use InvalidArgumentException;
use Payment99Bill\Exception\ConfigException;

final class Config implements GatewayConfigConcern
{
    private $endpoint = [
        'PROD'    => 'https://www.99bill.com',
        'SANDBOX' => 'https://sandbox.99bill.com',
    ];

    private $serviceUri = [
        'PAY'          => 'gateway/recvMerchantInfoAction.htm',
        'QUERY'        => 'apipay/services/gatewayOrderQuery?wsdl',
        'REFUND'       => 'webapp/receiveDrawbackAction.do',
        'QUERY_REFUND' => 'gatewayapi/services/gatewayRefundQuery?wsdl',
    ];

    protected $isRMB = false;
    protected $testMode = false;
    protected $privateKey;
    protected $publicKey;
    protected $merchantAcctId;

    public function __construct(
        $privateKeyPath, 
        $publicKeyPath, 
        $merchantId, 
        bool $isRmbAccount = false, 
        bool $isTest = false
    )
    {
        $this->setMerchantId($merchantId);
        $this->setPublicKey($publicKeyPath);
        $this->setPrivateKey($privateKeyPath);
        $this->setTestModeStatus($isTest);
        $this->setRmbAccountStatus($isRmbAccount);
    }

    /**
     * Set merchant account ID.
     *
     * @param  string $merchantId
     * @return static
     */
    public function setMerchantId($merchantId)
    {
        if (empty($merchantId) && is_string($merchantId)) {
            throw new InvalidArgumentException("merchant ID must be valid string");
        }

        $this->merchantAcctId = trim($merchantId);

        return $this;
    }

    /**
     * Set merchant account ID.
     *
     * @return string
     */
    public function getMerchantId() : string
    {
        return $this->merchantAcctId;
    }

    /**
     * Set testMode status
     *
     * @param  bool $testMode
     * @return static
     */
    public function setTestModeStatus(bool $testMode)
    {
        $this->testMode = $testMode;

        return $this;
    }

    /**
     * Get testMode status.
     *
     * @return bool
     */
    public function getTestModeStatus() : bool
    {
        return $this->testMode;
    }

    /**
     * Set private Key Using path.
     *
     * @param string $path
     * @throws ConfigException
     * @return static
     */
    public function setPrivateKey($path)
    {
        if (empty($path)) {
            throw new ConfigException("Invalid private key path", 402);
        }

        if (!file_exists($path)) {
            throw new ConfigException("Private Key file not found.", 404);
        }

        $this->privateKey = file_get_contents($path);
        if (empty($this->privateKey)) {
            throw new ConfigException("Private key content is empty", 500);
        }

        if (strpos($this->privateKey, '-----') === false) {
            $lines = [];
            $lines[] = '-----BEGIN RSA PRIVATE KEY-----';
            for ($i = 0; $i < strlen($this->privateKey); $i += 64) {
                $lines[] = trim(substr($this->privateKey, $i, 64));
            }
            $lines[] = '-----END RSA PRIVATE KEY-----';
            $this->privateKey = implode("\n", $lines);
        }

        return $this;
    }

    /**
     * Get Private Key string.
     *
     * @return string
     */
    public function getPrivatekey() : string
    {
        if (empty($this->privateKey)) {
            throw new ConfigException("Private key is not loaded yet", 403);
        }
        return $this->privateKey;
    }

    /**
     * Set public Key Using path.
     *
     * @param string $path
     * @throws ConfigException
     * @return static
     */
    public function setPublicKey($path)
    {
        if (empty($path)) {
            throw new ConfigException("Public key file is empty", 402);
        }

        if (!file_exists($path)) {
            throw new ConfigException("Public Key file not found.", 404);
        }

        $this->publicKey = file_get_contents($path);
        if (empty($this->publicKey)) {
            throw new ConfigException("Public key content is empty", 500);
        }

        if (strpos($this->publicKey, '-----') === false) {
            $lines = [];
            $lines[] = '-----BEGIN PUBLIC KEY-----';
            for ($i = 0; $i < strlen($this->publicKey); $i += 64) {
                $lines[] = trim(substr($this->publicKey, $i, 64));
            }
            $lines[] = '-----END PUBLIC KEY-----';
            $this->publicKey = implode("\n", $lines);
        }

        return $this;
    }

    /**
     * Get public Key string.
     *
     * @return string
     */
    public function getPublickey() : string
    {
        if (empty($this->publicKey)) {
            throw new ConfigException("Public key is not loaded yet", 403);
        }
        return $this->publicKey;
    }

    /**
     * Is RMB Account
     *
     * @return bool
     */
    public function isRmbAccount(): bool
    {
        return $this->isRMB;
    }

    /**
     * Set RMB Account status
     *
     * @param bool
     * @return static
     */
    public function setRmbAccountStatus(bool $value)
    {
        $this->isRMB = $value;

        return $this;
    }

    public function getEndpoint() : string
    {
        if ($this->getTestModeStatus()) {
            return $this->endpoint['SANDBOX'];
        }

        return $this->endpoint['PROD'];
    }
}
