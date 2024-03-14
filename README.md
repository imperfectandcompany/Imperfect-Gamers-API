# Imperfect-Gamers-Api

go to private/constants/system_constants.php
and set loggedIn to false. The dev_token is tied to user_id 19 in the db. if you cant find routes, its possible ur accessing non-authenticated routes while you're already logged into our dev environment so its looking for a token.

also, you need to create dbconfig.php, i have provided connection details in messages.

## Table of Contents
- [Router](#Router)
- [DevMode](#DevMode)

<a name="Router"></a>
## Router
Associated files:
`public_html/index.php`
`private/classes/class.router.php

### Function: add

Adds a new route to the router.

#### Parameters:

- `$uri` - The route URI
- `$controller` - The controller name and method, separated by `@`
- `$requestMethod` - The HTTP request method

### Adding Route (Smoke test):

1. Check if the URI is empty. If it is, throw an exception.
2. Add a slash to the beginning of the URI if it is missing.
3. Check if the URI ends with a slash and remove it if it does.
4. Check if the controller name and method are separated by `@`. If not, throw an exception.
5. Split the controller name and method into separate variables.
6. Check if a method name was provided after `@`. If not, throw an exception.
7. Check if a route with the same URI already exists. If it does, throw an exception.
8. Check if the endpoint matches with an existing route. If it does and the request method is already registered, throw an exception.
9. Get the list of files in the controllers directory.
10. Check if a file with the expected name exists.
11. If the file does not exist, throw an exception.
12. Check if the specified method exists in the controller. If not, throw an exception.
13. Check if the HTTP request method is valid. If not, throw an exception.
14. Parse the URI to find any parameter placeholders and save them to an array.
15. If the URI does not already exist in the routes array, add it along with the HTTP request method and controller name and method.
16. If the URI already exists in the routes array, add the HTTP request method and controller name and method to the existing URI.

### Required Parameters

Now, we can specify required parameters for different HTTP request methods (e.g., GET, POST, PUT) for each route. This ensures that the necessary data is present when handling requests.

Example:
```php
// Update an existing integration for the authenticated user
$router->enforceParameters('/integrations/:id', 'PUT', [
    'service' => 'body',   // Service comes from the request body
    'clientname' => 'body',   // Service comes from the request body
]);
```

### Documentation
We've added support for documenting our routes comprehensively. We can include documentation for each route, describing its purpose and usage.

Example:
```php
// Add documentation to route
$router->addDocumentation('/integrations/:id', 'PUT', 'Updates an existing integration for the authenticated user.');
```

### Required Parameters

Now, we can specify required parameters for different HTTP request methods (e.g., GET, POST, PUT) for each route. This ensures that the necessary data is present when handling requests.

Example:
```php
// Update an existing integration for the authenticated user
$router->enforceParameters('/integrations/:id', 'PUT', [
    'service' => 'body',   // Service comes from the request body
    'clientname' => 'body',   // Service comes from the request body
]);
```

### Enforcing Required Parameters
To ensure that required parameters are always present, we introduced a function that enforces them for a specific route and request method. This helps maintain data integrity and ensures that our routes receive the necessary input.

Example:
```php
// Require 'service' and 'clientname' to be present in the request body for the PUT method
$router->enforceParameters('/integrations/:id', 'PUT', [
    'PUT:body:service,clientname',
]);
```

<a name="DevMode"></a>
## Introduction of DevMode

With the implementation of a development mode (`devmode`), our RESTful Web Service is now endowed with a mode that makes it more streamlined and hassle-free for our developers during the application development phase.

### What is `devmode`?

`devmode` is a feature designed to simplify the development and testing process. When activated, it avoids the need for token-based authentication for each request, making it easier for developers to test different endpoints without having to worry about providing or refreshing authentication tokens. This can significantly speed up development, but it's essential to remember that `devmode` should **never** be activated in production environments, as it bypasses certain security checks.

### Endpoints:

1. **Get Current DevMode Status**
    - **Endpoint**: `/devmode`
    - **HTTP Method**: GET
    - **Description**: Retrieves the current status of `devmode`, returning whether it's turned on (`true`) or off (`false`).
    - **Usage**: 
      ```http
      GET /devmode
      ```

2. **Toggle DevMode**
    - **Endpoint**: `/devmode/toggle`
    - **HTTP Method**: GET
    - **Description**: Toggles the current `devmode` status. If it's on, it will be turned off and vice versa.
    - **Usage**:
      ```http
      GET /devmode/toggle
      ```

3. **Set DevMode to a Specific Value**
    - **Endpoint**: `/devmode/toggle/:value`
    - **HTTP Method**: GET
    - **Description**: Sets the `devmode` status to a specific value. The `:value` parameter should be replaced with either `true` or `false`.
    - **Usage**:
      ```http
      GET /devmode/toggle/true
      ```
      or
      ```http
      GET /devmode/toggle/false
      ```

### How to toggle `devmode`?

- To **check the current status**, use the `/devmode` endpoint.
  
- To **switch the current mode**, simply call the `/devmode/toggle` endpoint. It will invert the current setting.
  
- To **set a specific mode** (either `true` or `false`), use the `/devmode/toggle/:value` endpoint, replacing `:value` with your desired state.

---

**Important**: Always ensure that `devmode` is turned off (`false`) in production environments for security reasons.
