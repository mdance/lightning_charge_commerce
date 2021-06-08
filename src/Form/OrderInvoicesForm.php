<?php

namespace Drupal\lightning_charge_commerce\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Utility\TableSort;
use Drupal\lightning_charge\Events\LightningChargeEvents;
use Drupal\lightning_charge\Events\LightningChargeInvoiceBulkOperationsEvent;
use Drupal\lightning_charge\Form\InvoicesForm;
use Drupal\lightning_charge\InvoiceInterface;
use Drupal\lightning_charge\LightningChargeConstants;
use Drupal\lightning_charge\LightningChargeServiceInterface;
use Drupal\lightning_charge\MetaDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the OrderInvoicesForm class.
 */
class OrderInvoicesForm extends InvoicesForm {

  /**
   * Provides the event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Provides the module service.
   *
   * @var \Drupal\lightning_charge\LightningChargeServiceInterface
   */
  protected $service;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    LightningChargeServiceInterface $service
  ) {
    $this->eventDispatcher = $event_dispatcher;
    $this->service = $service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('lightning_charge')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lightning_charge_commerce_invoices_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, OrderInterface $commerce_order = NULL) {
    // @todo Add filters
    // @todo Add views integration
    // @todo Add pager
    $account = $this->currentUser();

    $key = 'operation';

    $operations = $this->getBulkOperations();

    $options = [];

    foreach ($operations as $operation) {
      $options[$operation->getKey()] = $operation->getLabel();
    }

    $form[$key] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#options' => $options,
      '#empty_option' => $this->t('Please select'),
      '#required' => TRUE,
    ];

    $key = 'items';

    $header = [];

    $header['id'] = [
      'field' => 'id',
      'data' => $this->t('ID'),
    ];

    $header['amount'] = [
      'field' => 'amount',
      'data' => $this->t('Amount'),
    ];

    $header['description'] = $this->t('Description');
    $header['create_date'] = [
      'field' => 'create_date',
      'data' => $this->t('Created'),
      'sort' => 'desc',
    ];

    $header['expiration_date'] = [
      'field' => 'expiration_date',
      'data' => $this->t('Expiration'),
    ];

    $header['payment_date'] = [
      'field' => 'payment_date',
      'data' => $this->t('Payment Date'),
    ];

    $header['satoshis_received'] = [
      'field' => 'satoshis_received',
      'data' => $this->t('Payment Amount (MSAT)'),
    ];

    $header['status'] = [
      'field' => 'status',
      'data' => $this->t('Status'),
    ];

    $header['operations'] = $this->t('Operations');

    $empty = $this->t('There are no invoices available at this time.');

    $options = [];

    try {
      $order_id = $commerce_order->id();

      $items = $this->service->invoices();

      foreach ($items as $k => $item) {
        $metadata = $item->getMetadata();

        $oid = $metadata['order_id'] ?? NULL;

        if ($oid instanceof MetaDataInterface) {
          $oid = $oid->getValue();
        }

        if ($oid != $order_id) {
          unset($items[$k]);
        }
      }

      $request = $this->getRequest();
      $context = TableSort::getContextFromRequest($header, $request);

      $field = $context['sql'];
      $sort = $context['sort'];

      uasort(
        $items,
        function(InvoiceInterface $a, InvoiceInterface $b) use ($field, $sort) {
          switch ($field) {
            case 'id':
              $av = $a->getId();
              $bv = $b->getId();

              break;
            case 'amount':
              $av = $a->getAmount();
              $bv = $b->getAmount();

              break;
            case 'create_date':
              $av = $a->getCreateDate();
              $bv = $b->getCreateDate();

              break;
            case 'expiration_date':
              $av = $a->getExpirationDate();
              $bv = $b->getExpirationDate();

              break;
            case 'payment_date':
              $av = $a->getPaymentDate();
              $bv = $b->getPaymentDate();

              break;
            case 'satoshis_received':
              $av = $a->getSatoshisReceived();
              $bv = $b->getSatoshisReceived();

              break;
            case 'status':
              $av = $a->getStatus();
              $bv = $b->getStatus();

              break;
          }

          if ($av == $bv) {
            return 0;
          } else if ($av > $bv) {
            return $sort == 'asc' ? 1 : -1;
          } else {
            return $sort == 'asc' ? -1 : 1;
          }
        }
      );

      foreach ($items as $item) {
        /** @var \Drupal\lightning_charge\Invoice $item */
        $option = [];

        $id = $item->getId();

        $route_parameters = [];

        $route_parameters['invoice'] = $id;

        $url_options = [];

        $url_options['attributes']['target'] = '_new';

        $url = Url::fromRoute('lightning_charge.invoice.view', $route_parameters, $url_options);

        $link = Link::fromTextAndUrl($id, $url);

        $option['id'] = $link;
        $option['amount'] = $item->getAmount();
        $option['description'] = $item->getDescription();
        $option['create_date'] = $item->getFormattedCreateDate();
        $option['expiration_date'] = $item->getFormattedExpirationDate();
        $option['payment_date'] = $item->getFormattedPaymentDate();
        $option['satoshis_received'] = $item->getSatoshisReceived() ?? '';
        $option['status'] = $item->getFormattedStatus();

        $operations = [];

        $route_parameters = [];

        $route_parameters['invoice'] = $id;

        if ($account->hasPermission(LightningChargeConstants::PERMISSION_VIEW)) {
          $url = Url::fromRoute('lightning_charge.invoice.view', $route_parameters);

          $operations['view'] = [
            'title' => $this->t('View'),
            'url' => $url,
          ];
        }

        if ($account->hasPermission(LightningChargeConstants::PERMISSION_EDIT)) {
          $url = Url::fromRoute('lightning_charge.invoice.edit', $route_parameters);

          $operations['edit'] = [
            'title' => $this->t('Edit'),
            'url' => $url,
          ];
        }

        if ($account->hasPermission(LightningChargeConstants::PERMISSION_DELETE)) {
          $url = Url::fromRoute('lightning_charge.invoice.delete', $route_parameters);

          $operations['delete'] = [
            'title' => $this->t('Delete'),
            'url' => $url,
          ];
        }

        $option['operations'] = [
          'data' => [
          '#type' => 'operations',
          '#links' => $operations,
          ],
        ];

        $options[$id] = $option;
      }
    } catch (\Exception $e) {
    }

    $form[$key] = [
      '#type' => 'tableselect',
      '#required' => TRUE,
      '#header' => $header,
      '#options' => $options,
      '#empty' => $empty,
      '#sticky' => TRUE,
    ];

    $form['objects'] = [
      '#type' => 'value',
      '#value' => $items,
    ];

    $key = 'actions';

    $form[$key] = [
      '#type' => 'actions',
    ];

    $actions = &$form[$key];

    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Gets the bulk operations.
   *
   * @return \Drupal\lightning_charge\LightningChargeBulkOperationInterface[]
   */
  private function getBulkOperations() {
    $output = [];

    $event = new LightningChargeInvoiceBulkOperationsEvent();

    $this->eventDispatcher->dispatch($event, LightningChargeEvents::BULK_OPERATIONS);

    $operations = $event->getOperations();

    foreach ($operations as $operation) {
      $output[$operation->getKey()] = $operation;
    }

    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    $operation = $values['operation'];
    $operations = $this->getBulkOperations();

    if (isset($operations[$operation])) {
      $operations[$operation]->validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValues();

    $operation = $values['operation'];
    $operations = $this->getBulkOperations();

    if (isset($operations[$operation])) {
      $operations[$operation]->submitForm($form, $form_state);
    }
  }

}
