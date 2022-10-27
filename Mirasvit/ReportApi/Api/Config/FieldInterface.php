<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-report-api
 * @version   1.0.54
 * @copyright Copyright (C) 2022 Mirasvit (https://mirasvit.com/)
 */



namespace Mirasvit\ReportApi\Api\Config;

interface FieldInterface
{
    /**
     * @return string
     */
    public function getIdentifier();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return TableInterface
     */
    public function getTable();

    /**
     * Whether the field is the primary key for a table or not.
     * @return bool
     */
    public function isIdentity();

    /**
     * @return string
     */
    public function toDbExpr();

    /**
     * @param SelectInterface $select
     * @return bool
     */
    public function join(SelectInterface $select);
}