<?php

namespace Payment99Bill;

interface GatewayConfigConcern
{
    public function getMerchantId() : string;

    public function getTestModeStatus() : bool;

    public function getPrivatekey() : string;

    public function  getPublickey() : string;

    public function isRmbAccount() : bool;

    public function getEndpoint(): string;
}
