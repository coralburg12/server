<?php
/**
 * @package deployment
 * @subpackage lynx.roles_and_permissions
 */

$addPermissionsAndItemsScript = realpath(dirname(__FILE__) . '/../../../../') . '/alpha/scripts/utils/permissions/addPermissionsAndItems.php';

$config = realpath(dirname(__FILE__)) . '/../../../permissions/service.schedule.scheduleResource.ini';
passthru("php $addPermissionsAndItemsScript $config");

$config = realpath(dirname(__FILE__)) . '/../../../permissions/service.schedule.scheduleEvent.ini';
passthru("php $addPermissionsAndItemsScript $config");