<?php
namespace MyApplication;

use SmartFactory\FactoryBuilder;
use SmartFactory\ConfigSettingsManager;
use SmartFactory\ApplicationSettingsManager;
use SmartFactory\UserSettingsManager;

use SmartFactory\DatabaseWorkers\DBWorker;

use function SmartFactory\dbworker;

use MyApplication\Interfaces\IUser;
use MyApplication\HotelXmlApiRequestHandler;

/**
 * This file contains the mapping of the custom implementing classes 
 * to the interfaces.
 *
 * @author Oleg Schildt
 *
 * @package Factory Methods
 */

//-------------------------------------------------------------------
FactoryBuilder::bindClass(HotelXmlApiRequestHandler::class, HotelXmlApiRequestHandler::class);
//-------------------------------------------------------------------
FactoryBuilder::bindClass(ConfigSettingsManager::class, ConfigSettingsManager::class, function($instance) {
  $instance->init(["save_path" => APPLICATION_ROOT . "config/settings.xml",
                   "config_file_must_exist" => false
                   //"save_encrypted" => true,
                   //"salt_key" => "demotest"
                  ]);
  $instance->loadSettings();

  $instance->setValidator(new ConfigSettingsValidator());
});
//-------------------------------------------------------------------
FactoryBuilder::bindClass(ApplicationSettingsManager::class, ApplicationSettingsManager::class, function($instance) {
  $instance->init(["dbworker" => dbworker(),
                   "settings_table" => "SETTINGS",
                   "settings_column" => "DATA"
                  ]);
  $instance->loadSettings();

  $instance->setValidator(new ApplicationSettingsValidator());
});
//-------------------------------------------------------------------
FactoryBuilder::bindClass(UserSettingsManager::class, UserSettingsManager::class, function($instance) {
  $instance->init(["dbworker" => dbworker(),
                   "user_table" => "USERS",
                   "settings_fields" => [
                      "ID" => DBWorker::db_number,
                      "SIGNATURE" => DBWorker::db_string,
                      "STATUS" => DBWorker::db_string,
                      "HIDE_PICTURES" => DBWorker::db_number,
                      "HIDE_SIGNATURES" => DBWorker::db_number,
                      "LANGUAGE" => DBWorker::db_string,
                      "TIME_ZONE" => DBWorker::db_string
                   ],
                   "user_id_field" => "ID",
                   "user_id_getter" => function() { return 1; }
                  ]);
  $instance->loadSettings();

  $instance->setValidator(new UserSettingsValidator());
});

//-------------------------------------------------------------------
FactoryBuilder::bindClass(IUser::class, User::class);
//-------------------------------------------------------------------
?>