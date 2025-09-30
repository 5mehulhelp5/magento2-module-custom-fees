<?php

declare(strict_types=1);

use JosephLeedy\CustomFees\Model\FeeType;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DB\Transaction;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$resolver = Resolver::getInstance();

$resolver->requireDataFixture(
    'JosephLeedy_CustomFees::../test/Integration/_files/multiple_creditmemos_with_custom_fees.php',
);

/** @var ObjectManagerInterface $objectManager */
$objectManager = Bootstrap::getObjectManager();
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$orderSearchResults = $searchCriteriaBuilder
    ->addFilter(
        'increment_id',
        [
            '100000001',
            '100000002',
            '100000003',
            '100000004',
            '100000005',
            '100000006',
        ],
        'in',
    )->create();
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->create(OrderRepositoryInterface::class);
$orderCollection = $orderRepository->getList($orderSearchResults);
/** @var Transaction $transaction */
$transaction = $objectManager->create(Transaction::class);

$orderCollection->walk(
    static function (Order $order) use ($transaction): void {
        $customOrderFees = $order->getExtensionAttributes()?->getCustomOrderFees();
        $refundedCustomFees = [];

        if ($customOrderFees === null) {
            return;
        }

        $order
            ->getCreditmemosCollection()
            ->walk(
                static function (Creditmemo $creditmemo) use (&$refundedCustomFees): void {
                    $creditmemoId = (int) $creditmemo->getEntityId();

                    $refundedCustomFees[$creditmemoId] = [
                        'test_fee_0' => [
                            'credit_memo_id' => $creditmemoId,
                            'code' => 'test_fee_0',
                            'title' => 'Test Fee',
                            'type' => FeeType::Fixed->value,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 5.00,
                            'value' => 5.00,
                        ],
                        'test_fee_1' => [
                            'credit_memo_id' => $creditmemoId,
                            'code' => 'test_fee_1',
                            'title' => 'Another Test Fee',
                            'type' => FeeType::Fixed->value,
                            'percent' => null,
                            'show_percentage' => false,
                            'base_value' => 1.50,
                            'value' => 1.50,
                        ],
                    ];
                },
            );

        $customOrderFees->setCustomFeesRefunded($refundedCustomFees);

        $transaction->addObject($customOrderFees);
        $transaction->save();
    },
);
