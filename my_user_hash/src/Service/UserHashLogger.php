<?php

namespace Drupal\my_user_hash\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

class UserHashLogger {
  protected $loggerFactory;
  protected $messenger;

  public function __construct(
    LoggerChannelFactoryInterface $logger_factory, 
    MessengerInterface $messenger
  ) {
    $this->loggerFactory = $logger_factory;
    $this->messenger = $messenger;
  }

  public function log($message, array $context = []) {
    // Log to Drupal's log system
    $this->loggerFactory->get('my_user_hash')->info($message, $context);
    
    // Optionally display a message to the user
    $this->messenger->addMessage($message, 'status');
  }
}
