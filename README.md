## SmartFactory

- Lightweight simple PHP framework
- With many useful tools and functions
- Without overhead
- Designed based on IoC 

For more details see [Wiki](https://github.com/oschildt/SmartFactory/wiki).

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
