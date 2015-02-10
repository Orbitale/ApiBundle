
Pierstoval ApiBundle
==================

Allows the developer to create simple webservices based on an entities list in configuration.


Installation
-------------------------

**With Composer**

Just write this command line instruction if Composer is installed in your Symfony root directory :

```sh
composer require pierstoval/api-bundle
```

I recommend to use Composer, as it is the best way to keep this repository update on your application.
You can easily download Composer by checking [the official Composer website](http://getcomposer.org/)

Initialization
-------------------------

You need to initiate some settings to make sure the bundle is configured properly. 

1. First, register the required bundles in the kernel:

    ```php
    <?php
    # app/AppKernel.php
    class AppKernel extends Kernel
    {
        public function registerBundles() {
            $bundles = array(
                // ...
                new FOS\RestBundle\FOSRestBundle(),
                new Pierstoval\Bundle\ApiBundle\PierstovalApiBundle(),

    ```
2. Import the routing in your application:

    ```yaml
    # app/config/routing.yml
    pierstoval_api:
        resource: "@PierstovalApiBundle/Controller/"
        type:     annotation
        prefix:   /api/
    ```
    The prefix is up to you, but it's important that the app has to be solitary in its relative path, for it to work in the best way.
3. Done ! The rest is all specific configuration for your application.

Usage
-------------------------

Add your first entity in the config:

```yaml
# app/config/config.yml
pierstoval_api:
    services:
        posts: { entity: AppBundle\Entity\Post }
```

Run your server:
```shell
$ php app/console server:run
```

Navigate to its generated url `http://127.0.0.1/app_dev.php/api/post`.

Now you should see something like this:

```json
{"posts":[]}
```

If you do see this, it means that the API generator is working !

HTTP methods
-------------------------

The generator handles **GET**, **POST** (update), **PUT** (insert) and **DELETE** HTTP methods.

### GET routes

* `pierstoval_api_cget` : `/{serviceName}`
    
    This route allows to get a collection of objects of the specified entity.
    
    The received collection has the same key as the `serviceName`, and is an array of objects.

* `pierstoval_api_get` : `/{serviceName}/{id}`
    
    This route retrieves a single object with its primary key (even if this primary key is not called `id`). The received element will have the same key as the `serviceName` but with removed trailing 's' at the end of it (for example, `posts` will become `post`).
    
    All the received attributes in the object will follow your `ExclusionPolicy` and different `Expose` or `Exclude` settings in the `jms_serializer`.
    
    If you need more information about exposing or not some fields, you can check [JMSSerializer's documentation](http://jmsyst.com/libs/serializer/master/reference/annotations)

* `pierstoval_api_get_subrequest` : `/{serviceName}/{id}/{subElement}`

    This is the great point of this Api generator.
    
    This route can retrieve any element recursively depending on three parameters:
    * The parameter has to be a valid entity attribute.
    * If the attribute is a collection, you can fetch one element in this collection by appending its primary key.
    * The value must not be null (or it'll return an empty value).

    For example, if your `Page` entity has a `title` attribute, you can type this url: `http://127.0.0.1:8000/api/pages/1/title`.
    
    And you may see something like this:
    
    ```json
    {"page.1.title":"Default page"}
    ```
    
    As the `subRequest` is managed recursively, you really can navigate in a complex object like this:
    `http://127.0.0.1:8000/api/pages/1/children/2/category/name`.
    It will then retrieve datas in the specified order :
    * Get the `page` element with primary key `1`.
    * Get its element `children`, which is a collection of `Page`.
    * Retrieve the one with the primary key `2`.
    * Get the `Page 2`'s category object.
    * Get the category name.

    The output may look like this:
    ```json
    {"page.1.children.2.category.name": "Default category"}
    ```
    
    The object's key is the compilation of your request, so you can check whether it exists in your code, and if it does, it means that it's a valid object.
    
### POST and PUT routes

The POST route is only used to UPDATE datas.
The PUT route is only used to INSERT datas.

Basically, the PUT route works the same than the POST route, but it won't merge any entity in the database. It will instead fill an empty entity, validate it, and if the object is valid, persist it.

* POST: `pierstoval_api_post` : `/{serviceName}/{id}`
* PUT: `pierstoval_api_put` : `/{serviceName}`

The route will first search for an entity of `serviceName` with primary key `id`.


##### The `json` object 
The API will search for a `json` parameter in POST datas. If it's a string, it's automatically transformed into a Json object.

This `json` object is a transposition of your entity serialized object, with its values.

It means that you can use your entity attributes as they're shown by the Api generator.
It will simply `merge` (if POST) or fill an empty object (if PUT) to the database object with your json object, so it won't modify other parameters.

##### The `mapping` object

Additionally, the Api will search for a `mapping` object. This object is mandatory, and it defines all the fields you want to 

For example, if you only want to modify a `Page` `title` attribute, you can use this json object:

```json
{ "json": { "title": "New title !" }, "mapping": { "title": true } }
```

Then, you will change the title, and you won't modify any other data directly.

The entire object will be then sent to validation through the Symfony's `validator` service, so you can see if the object is wrongly updated or not, by simply using your usual validation annotations or files.

If the object is not valid, the API will send you all the error messages sent by the `validator` service, for you to handle them in front (or back, depending on how you manage to use this bundle).

The output is the newly updated or inserted object.

### Mapping-specific behavior

Sometimes you use `camelCase`, sometime `snake_case`, and `jms_serializer` can be configured differently in different apps.
 
This is why some attributes may have their name changed through the serialization process.
 
One simple case :
 
In your `Page` entity, you have a `ManyToOne` relationship with a `Category` object.

Your mapping looks like this:

```php
# AppBundle\Entity\Page.php
// ...
    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Category")
    */
    protected $pageCategory;
```

When retrieving a `Page` object with the API, you'll see this :

```json
{ "page": { "title": "Page title", "page_category": { "id": 1, "name": "Default category" } } }
```

This can have some breaks, does it?

Then, when you **send** your `JSON` object to any of the PUT or POST methods, you'll have to tell the API that the fields have different names.

For example, in a POST request:

```json
{
    "json": { "page_category": { "id": 1, "name": "Sefault category" } },
    "mapping": { "page_category": { "objectField": "pageCategory" } }
}
```

With this special mapping for `page_category`, you will tell the API that the `page_category` json attribute corresponds to a `pageCategory` attribute in your Doctrine entity.


### Relationships
    
As no automatic cascading operation is made, you'll have to specify it in your Entity mapping.

Plus, the object must be an existing object, or you can have some unexpected behavior.


## Secure your API

In the configuration reference you may see a `allowed_origins` attribute.

This attribute is used to check whether the asker has rights to view this API or not. It's especially useful to refuse connections from some IP addresses, and from unwanted AJAX requests (CORS is not managed, you'll have to use another bundle for that).

By default, in `dev` environment, localhosts are automatically added to the `allowed_origins` array.

The current server IP address is also added to the `allowed_origins`, for you to make requests to your own API from your own server.

You can add other IPs or domain names in this attribute like this:

```yaml
# app/config/config.yml
pierstoval_api:
    allowed_origins: 
        - 1.2.3.4
        - my.domain.com
```

This is a _basic_ security system. If you want more security, you'll have to extend the [ApiController](Controller/ApiController.php) and override the `checkAsker` method, and also change the routing namespace to your own controller.

Conclusion
-------------------------

You can also view this repository [on its Packagist.org page](https://packagist.org/packages/pierstoval/api-bundle), even though it's not really useful to see.

Feel free to send me a mail at pierstoval@gmail.com if you have any question !! (I LOVE questions, really, feel free to ask !)
 
If you find this bundle to be cool, feel free to propose improvements and send pull-requests !

Thanks for reading and using !

Pierstoval.
