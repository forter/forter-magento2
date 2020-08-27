<?php
/**
 * Forter Payments For Magento 2
 * https://www.Forter.com/
 *
 * @category Forter
 * @package  Forter_Forter
 * @author   Girit-Interactive (https://www.girit-tech.com/)
 */

namespace Forter\Forter\Model\RequestBuilder;

/**
 * Class GiftCard
 *
 * @package Forter\Forter\Model\RequestBuilder
 */
class GiftCard {
    /**
     * @param \Magento\Sales\Model\Order\Item $item
     *
     * @return bool
     */
    public function isGiftCard($item)
    {
        $productOptions = $item->getProductOptions();

        return $productOptions !== null && !empty($productOptions["giftcard_recipient_name"]) && !empty($productOptions["giftcard_recipient_email"]);
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     *
     * @return array
     */
    public function getGiftCardBeneficiaries($item)
    {
        $data          = $this->formatData($item);
        $beneficiaries = [
            "personalDetails" => [
                "firstName" => $data["firstName"],
                "lastName"  => $data["lastName"],
                "email"     => $data["email"]
            ]
        ];

        if ($message = $data["message"]) {
            $beneficiaries["comments"]["messageToBeneficiary"] = $message;
        }

        return [$beneficiaries];
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     *
     * @return array|null
     */
    public function getGiftCardPrimaryRecipient($order)
    {
        foreach ($order->getAllItems() as $item) {
            if ($this->isGiftCard($item)) {
                $data = $this->formatData($item);

                return [
                    "firstName" => $data["firstName"],
                    "lastName"  => $data["lastName"],
                    "email"     => $data["email"]
                ];
            }
        }

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order\Item $item
     *
     * @return array
     */
    private function formatData($item)
    {
        $productOptions = $item->getProductOptions();
        $name           = explode(" ", $productOptions["giftcard_recipient_name"], 2);

        return [
            "firstName" => !empty($name[0]) ? (string)$name[0] : "",
            "lastName"  => !empty($name[1]) ? (string)$name[1] : "",
            "email"     => (string)$productOptions["giftcard_recipient_email"],
            "message"   => !empty($productOptions["giftcard_message"]) ? $productOptions["giftcard_message"] : ""
        ];
    }
}
