<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */
declare(strict_types=1);

namespace Magefan\BirthdayEmail\Model;

use Psr\Log\LoggerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magefan\BirthdayEmail\Api\EmailSenderInterface;

/**
 * Class EmailSender
 */
class EmailSender implements EmailSenderInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * EmailSender constructor.
     * @param LoggerInterface $logger
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Emulation $emulation
     */
    public function __construct(
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        Emulation $emulation
    ) {
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->emulation = $emulation;
    }

    /**
     * Send birthday email to customer
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return bool
     */
    public function send(\Magento\Customer\Api\Data\CustomerInterface $customer): void
    {
        $enabled = $this->scopeConfig->getValue(
            'mfbirthdayemail/email/enabled',
            ScopeInterface::SCOPE_STORE,
            $customer->getStoreId()
        );
        if (!$enabled) {
            return;
        }

        $this->inlineTranslation->suspend();
        $this->emulation->startEnvironmentEmulation($customer->getStoreId());

        try {
            $senderIdentity = $this->scopeConfig->getValue(
                'mfbirthdayemail/email/sender',
                ScopeInterface::SCOPE_STORE,
                $customer->getStoreId()
            );
            $this->transportBuilder
                ->setTemplateIdentifier(
                    $this->scopeConfig->getValue(
                        'mfbirthdayemail/email/template',
                        ScopeInterface::SCOPE_STORE,
                        $customer->getStoreId()
                    )
                )
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                        'store' => $customer->getStoreId(),
                    ]
                )
                ->setTemplateVars($vars = [
                    'customer' => $customer,
                    'store' => $this->storeManager->getStore($customer->getStoreId())
                ])
                ->setFrom(
                    [
                        'name' => $this->scopeConfig->getValue(
                            'trans_email/ident_' . $senderIdentity . '/name',
                            ScopeInterface::SCOPE_STORE,
                            $customer->getStoreId()
                        ),
                        'email' => $this->scopeConfig->getValue(
                            'trans_email/ident_' . $senderIdentity . '/email',
                            ScopeInterface::SCOPE_STORE,
                            $customer->getStoreId()
                        ),
                    ]
                )
                ->addTo($customer->getEmail());

            $copyTo = (string)$this->scopeConfig->getValue(
                'mfbirthdayemail/email//copy_to',
                ScopeInterface::SCOPE_STORE,
                $customer->getStoreId()
            );

            $copyTo = explode(',', $copyTo);
            foreach ($copyTo as $to) {
                $to = trim($to);
                if ($to) {
                    $this->transportBuilder->addBcc($copyTo);
                }
            }

            $transport = $this->transportBuilder->getTransport();
            $transport->sendMessage();

        } catch (\Exception $e) {
            $this->logger->debug('Cannot send  Birthday Email to the customer ID ' . $customer->getId() . '. Error: ' . $e->getMessage());
        }
        $this->inlineTranslation->resume();
        $this->emulation->stopEnvironmentEmulation();
    }
}
