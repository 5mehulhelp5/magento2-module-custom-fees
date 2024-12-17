<?php

declare(strict_types=1);

namespace JosephLeedy\CustomFees\Plugin\Framework\View\Element\UiComponent\DataProvider;

use InvalidArgumentException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

use function array_walk;

class DataProviderPlugin
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly PriceCurrencyInterface $priceCurrency,
    ) {}

    /**
     * @param DataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(DataProvider $subject, array $result): array
    {
        if ($subject->getName() !== 'sales_order_grid_data_source') {
            return $result;
        }

        array_walk(
            $result['items'],
            function (array &$orderData): void {
                /** @var string|null $customFeesJson */
                $customFeesJson = $orderData['custom_fees'];

                if ($customFeesJson === null) {
                    return;
                }

                try {
                    /**
                     * @phpcs:ignore Generic.Files.LineLength.TooLong
                     * @var array<string, array{code: string, title: string, base_value: float, value: float}> $customFees
                     */
                    $customFees = $this->serializer->unserialize($customFeesJson);
                } catch (InvalidArgumentException) {
                    return;
                }

                array_walk(
                    $customFees,
                    /**
                     * @param array{code: string, title: string, base_value: float, value: float} $customFee
                     */
                    function (array $customFee) use (&$orderData): void {
                        $orderData[$customFee['code'] . '_base'] = $this->priceCurrency->format(
                            amount: $customFee['base_value'],
                            includeContainer: false,
                            scope: $orderData['store_id'],
                            currency: $orderData['base_currency_code']
                        );
                        $orderData[$customFee['code']] = $this->priceCurrency->format(
                            amount: $customFee['value'],
                            includeContainer: false,
                            scope: $orderData['store_id'],
                            currency: $orderData['order_currency_code']
                        );
                    }
                );
            }
        );

        return $result;
    }
}
