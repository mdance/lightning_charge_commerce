<?php

namespace Drupal\lightning_charge_commerce\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentReceiveForm;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\lightning_charge\LightningChargeServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LightningChargePaymentReceiveForm extends PaymentReceiveForm implements ContainerInjectionInterface {

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge\LightningChargeServiceInterface
   */
  protected $service;

  /**
   * {@inheritDoc}
   */
  public function __construct(LightningChargeServiceInterface $service) {
    $this->service = $service;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lightning_charge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    $form['#success_message'] = $this->t('Payment received.');

    $id = $payment->getRemoteId();

    if ($id) {
      $invoice = $this->service->invoice($id);

      $render = $invoice->toRenderable();

      $key = 'payment-request';

      if (isset($render[$key])) {
        $form[$key] = $render[$key];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

}
