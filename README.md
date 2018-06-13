## SmartFactory

- Lightweight simple PHP framework
- With many useful tools and functions
- Without overhead
- Designed based on IoC 

For more details see [Presentation](https://docs.google.com/presentation/d/1CcVX_bQQirFG0fq0CSQ2O7YTONQywyDtVJkai1GQhOM) and
[API documentation](http://php-smart-factory.org/apidoc/).

### Requirements

PHP 7.2.x

### To get familiar with the SmartFactory do the following

- View and study the usage examples in the folder examples.
- Use the script database/restore_database.cmd to create a demo database necessary for some examples.
- View and study the API documentation in the folder docs.
- Study the core code of the framework SmartFactory.

### To start writing own application

1. Put the clean directory structure of the framework SmartFactory into the root directory of your web application.
Use namespace for your application, e.g. MyApplication.

2. Create the directory MyApplication in the directory classes. Put your classes into this directory. Use PSR4 approach. You have no need to register additional class loader function.

3. Include any additional utility function files into the file user_includes_inc.php if necessary. 

4. Bind you classes to the interfaces in the file user_factory_inc.php to be able to use the IoC approach for creating objects offered by the framework SmartFactory.

5. Implement you business logic in the root directory or any subdirectory. Include the file includes/_general_inc.php in any of your business logic file.

7. Implement the API request handles in api/handlers and xmlapi/handlers if necessary.
Add translation texts for your application over the localization/edit.php or directly into the XML file localization/texts.xml.

## Directory Structure 

```
api
  handlers
classes
  SmartFactory
    Interfaces
    DatabaseWorkers
config
docs
examples
includes
  SmartFactory
localization
logs
xmlapi
  handlers
```

## Detailed description

### api
This directory contains the processor index.php of the API requests and the directory handlers where the user can implement his handlers of the API requests.

### api/handlers
This directory contains the files where the user implements his handlers of the API requests.

### classes
This is the root directory for all classes and interfaces. The class loader is implemented based on PSR4 approach. You have no need to add additional class loader function for your classes.

### classes/SmartFactory
This directory contains the core classes and interfaces of the framework SmartFactory.

### classes/SmartFactory/Interfaces
This directory contains the core interfaces of the framework SmartFactory.

### classes/SmartFactory/DatabaseWorkers
This directory contains the core classes of the framework SmartFactory for working with databases.

### config
This directory contains the configuration files.

### docs
This directory contains the documentation about classes, interfaces and functions of the framework SmartFactory.

### examples
This directory contains the examples of usage of the framework SmartFactory.

### includes
This directory contains the general include files. The main file is _general_inc.php. You should include it in every of your files of the business logic.

### includes/SmartFactory
This directory contains the core include files of the framework SmartFactory.

### localization
This directory contains the translation file texts.xml and the editor edit.php for user friendly editing of the translation texts.

### logs
This directory is used for logging, debugging and tracing.

### xmlapi
This directory contains the processor index.php of the XML API requests and the directory handlers where the user can implement his handlers of the XML API requests.

### xmlapi/handlers
This directory contains the files where the user implements his handlers of the XMLAPI requests.
