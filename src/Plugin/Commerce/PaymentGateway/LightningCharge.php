<?php

namespace Drupal\lightning_charge_commerce\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;
use Drupal\commerce_price\Price;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lightning_charge\LightningChargeServiceInterface;
use Drupal\token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the lightning charge payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "lightning_charge",
 *   label = "Lightning Charge",
 *   display_label = "Lightning Charge",
 *   forms = {
 *     "add-payment" = "Drupal\lightning_charge_commerce\PluginForm\LightningChargePaymentAddForm",
 *     "receive-payment" = "Drupal\lightning_charge_commerce\PluginForm\LightningChargePaymentReceiveForm",
 *   },
 *   payment_type = "lightning_charge_payment",
 *   payment_method_types = {"lightning_network"},
 *   requires_billing_information = FALSE,
 * )
 */
class LightningCharge extends Manual implements LightningChargeInterface {

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge\LightningChargeServiceInterface
   */
  protected $service;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PaymentTypeManager $payment_type_manager,
    PaymentMethodTypeManager $payment_method_type_manager,
    TimeInterface $time,
    TokenInterface $token,
    LightningChargeServiceInterface $service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time, $token);

    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_payment_type'),
      $container->get('plugin.manager.commerce_payment_method_type'),
      $container->get('datetime.time'),
      $container->get('token'),
      $container->get('lightning_charge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $states = [
      'new',
    ];

    $this->assertPaymentState($payment, $states);

    $props = [
      'metadata' => [],
      // @todo Set this from a configuration setting
      'expire' => 3600,
    ];

    $metadata = &$props['metadata'];

    $order = $payment->getOrder();

    $order_id = $order->id();

    $metadata['order_id'] = $order_id;

    $amount = $payment->getAmount();

    $props['currency'] = $amount->getCurrencyCode();
    $props['amount'] = $amount->getNumber();

    $args = [];

    $args['@order_id'] = $order_id;

    $props['description'] = $this->t('Order @order_id Payment', $args);

    try {
      /** @var \Drupal\lightning_charge\InvoiceInterface $invoice */
      $invoice = $this->service->createInvoice($props);

      $remote_id = $invoice->getId();

      $next_state = 'pending';

      $payment->setState($next_state);
      $payment->setRemoteId($remote_id);

      $payment->save();
    } catch (\Exception $e) {
      $message = 'An error occurred attempting to create an invoice';

      throw new PaymentGatewayException($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentInstructions(PaymentInterface $payment) {
    $output = parent::buildPaymentInstructions($payment);

    $id = $payment->getRemoteId();

    if ($id) {
      try {
        $invoice = $this->service->invoice($id);

        if ($invoice) {
          $render = $invoice->toRenderable();

          $key ='payment-request';

          if (isset($render[$key])) {
            $output[$key] = $render[$key];
          }
        }
      } catch (\Exception $e) {}
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaymentOperations(PaymentInterface $payment) {
    $output = parent::buildPaymentOperations($payment);

    $keys = [
      'void',
      'refund',
    ];

    foreach ($keys as $key) {
      if (isset($output[$key])) {
        unset($output[$key]);
      }
    }

    return $output;
  }

}
