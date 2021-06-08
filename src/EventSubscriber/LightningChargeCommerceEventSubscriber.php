<?php

namespace Drupal\lightning_charge_commerce\EventSubscriber;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\lightning_charge\Events\LightningChargeEvents;
use Drupal\lightning_charge\Events\LightningChargeInvoiceUpdateEvent;
use Drupal\lightning_charge\LightningChargeConstants;
use Drupal\lightning_charge\MetaDataInterface;
use Drupal\lightning_charge_commerce\LightningChargeCommerceConstants;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides the LightningChargeCommerceEventSubscriber class.
 */
class LightningChargeCommerceEventSubscriber implements EventSubscriberInterface {

  /**
   * Provides the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Provides the order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The log storage.
   *
   * @var \Drupal\commerce_log\LogStorageInterface
   */
  protected $logStorage;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $output = [];

    $output[LightningChargeEvents::INVOICE_UPDATE] = 'processInvoice';

    return $output;
  }

  /**
   * Processes an invoice.
   *
   * @param \Drupal\lightning_charge\Events\LightningChargeInvoiceUpdateEvent $event
   *   Provides the event object.
   */
  public function processInvoice(LightningChargeInvoiceUpdateEvent $event) {
    $invoice = $event->getInvoice();

    $id = $invoice->getId();
    $status = $invoice->getStatus();
    $metadata = $invoice->getMetadata();
    $amount = $invoice->getAmount();
    $currency = $invoice->getCurrency();
    //$received = $invoice->getSatoshisReceived();
    $payment_date = $invoice->getPaymentDate();

    $order_id = $metadata['order_id'] ?? NULL;

    if ($order_id instanceof MetaDataInterface) {
      $order_id = $order_id->getValue();
    }

    $order = NULL;

    if (is_numeric($order_id)) {
      $order = $this->orderStorage->load($order_id);
    }

    switch ($status) {
      case LightningChargeConstants::STATUS_UNPAID:
        $new_state = 'pending';

        break;
      case LightningChargeConstants::STATUS_PAID:
        $new_state = 'completed';

        break;
      case LightningChargeConstants::STATUS_EXPIRED:
        $new_state = 'pending';

        break;
      default:
        $new_state = 'new';

        break;
    }

    if (empty($currency)) {
      $currency = LightningChargeConstants::CURRENCY_USD;
    }

    $price = new Price($amount, $currency);

    $found = FALSE;

    // First check for an existing payment
    $storage = $this->entityTypeManager->getStorage('commerce_payment');

    $query = $storage->getQuery();

    $query->condition('remote_id', $id);

    $ids = $query->execute();

    $results = $storage->loadMultiple($ids);

    if ($results) {
      foreach ($results as $result) {
        $found = TRUE;

        /** @var \Drupal\commerce_payment\Entity\PaymentInterface $result */
        $save = FALSE;

        $remote_state = $result->getRemoteState();
        $completed_time = $result->getCompletedTime();
        $state = $result->getState()->getId();
        $existing_amount = $result->getAmount();

        if ($status != $remote_state) {
          $result->setRemoteState($status);

          $save = TRUE;
        }

        if ($completed_time != $payment_date) {
          $result->setCompletedTime($payment_date);

          $save = TRUE;
        }

        if ($state != $new_state) {
          $result->setState($new_state);

          $save = TRUE;
        }

        if ($existing_amount != $price) {
          $result->setAmount($price);

          $save = TRUE;
        }

        if ($save) {
          $result->save();

          if ($order) {
            $this->logStorage->generate($order, LightningChargeCommerceConstants::LOG_ORDER_PAID)->save();
          }
        }
      }
    }

    if (!$found && $order_id) {
      if ($order) {
        $uid = $order->getCustomerId();
        $storage = $this->entityTypeManager->getStorage('commerce_payment_gateway');

        $payment_gateway_id = 'lightning_network';

        /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
        $payment_gateway = $storage->load($payment_gateway_id);
        $payment_gateway_plugin = $payment_gateway->getPlugin();
        $payment_gateway_mode = $payment_gateway_plugin->getMode();

        $values = [];

        $values['state'] = $new_state;
        $values['amount'] = $price;
        $values['payment_gateway'] = $payment_gateway_id;
        $values['payment_gateway_mode'] = $payment_gateway_mode;
        $values['order_id'] = $order_id;
        $values['remote_id'] = $id;
        $values['expires'] = 0;
        $values['uid'] = $uid;

        $payment = Payment::create($values);

        $payment->save();

        $this->logStorage->generate($order, LightningChargeCommerceConstants::LOG_ORDER_PAID)->save();
      }
    } else {
      // @todo Implement logging
    }
  }

}
