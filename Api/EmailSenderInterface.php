<?php
/**
 * Copyright © Magefan (support@magefan.com). All rights reserved.
 * Please visit Magefan.com for license details (https://magefan.com/end-user-license-agreement).
 */
declare(strict_types=1);

namespace Magefan\BirthdayEmail\Api;

/**
 * Send the birthday email to a customer
 */
interface EmailSenderInterface
{
    /**
     * Send birthday email to customer
     *
     * @param \Magento\Customer\Api\Data\CustomerInterface $customer
     * @return void
     */
    public function send(\Magento\Customer\Api\Data\CustomerInterface $customer): void;
}
