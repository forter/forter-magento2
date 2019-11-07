<?php

namespace Forter\Forter\Model;

use Forter\Forter\Model\Config as ForterConfig;

class AbstractApi
{


    public function sendApiRequest()
    {

    }

    private function getHeaders()
    {
      $headers = [
          "x-forter-siteid: " . $this->configHelper->getSiteID(),
          "api-version: " . $this->configHelper->getApiVersion(),
          "x-forter-extver: " . $this->configHelper->getExtensionVersion(),
          "x-forter-client: magento2"
      ];
    }

    private function getCurlConfiguration()
    {

    }

}
