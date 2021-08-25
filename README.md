## SmartFactory

- Lightweight simple PHP library
- With many useful tools and functions
- Without overhead
- Designed based on IoC 

For more details see [Presentation](http://php-smart-factory.org/smartfactory_presentation.pdf) and
[API documentation](http://php-smart-factory.org/smartfactory/).

### Requirements

- PHP 7.2.x

### Installation

```
composer require smartfactory/smartfactory"
```

**composer.json**
 
```
{
  ...

  "require": {
    "php": ">=7.2",
    "smartfactory/smartfactory": ">=2.1.13"
  }
  
  ...
}
```

### To get familiar with the SmartFactory do the following:

- Git-clone the demo application [SmartFactoryDemo](https://github.com/oschildt/SmartFactoryDemo) and run 'composer update'.
- Use the script *database/create_database_mysql.sql* (*create_database_mssql.sql*) to create a demo database necessary for some examples.
- View and study the API documentation in the folder docs or here [API documentation](http://php-smart-factory.org/smartfactory/).
- Study the core code of the library SmartFactory.

### To start writing own application using SmartFactory

1. Git-clone the demo application [SmartFactoryDemo](https://github.com/oschildt/SmartFactoryDemo) and run 'composer update'.

2. Study the directory structure of the demo application and the code.

3. Implement your classes and functions. 

4. Bind you classes to the interfaces in the file *initialization_inc.php* to be able to use the IoC approach for creating objects offered by the library SmartFactory.

5. Implement you business logic in the root directory or any subdirectory. 

7. Implement the API request handlers for JSON or XML requests if necessary.

8. Add translation texts for your application over the *localization/edit.php* or directly into the JSON file *localization/texts.json*. Use the script *localization/check.php* to check your translations for missing translations.

## Directory Structure 

```
docs
src
  SmartFactory
    Interfaces
    DatabaseWorkers
```

## Detailed description

### docs
This directory contains the documentation about classes, interfaces and functions of the library SmartFactory.

### src
This is the root directory for all classes and interfaces. The class loader is implemented based on PSR4 approach. You have no need to add additional class loader function.

### src/SmartFactory
This directory contains the core classes and interfaces of the library SmartFactory.

### src/SmartFactory/Interfaces
This directory contains the core interfaces of the library SmartFactory.

### src/SmartFactory/DatabaseWorkers
This directory contains the core classes of the library SmartFactory for working with databases.
