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

**Important**: Always ensure that `devmode` is turned off (`false`) in production environments for security reasons






## Routes

### [https://api.imperfectgamers.org/user/changeusername](https://api.imperfectgamers.org/user/changeusername)

The `changeUsername` endpoint is responsible for facilitating the process of altering a user's username within the Imperfect Gamers platform. This endpoint is integral to web applications where clients initiate requests to modify usernames.

#### Functionality

- Upon receiving a request, the function first retrieves the user's ID and current username from global variables.
- It parses the request body, expecting a JSON object, and extracts the new username from it.
- Several checks are performed on the new username:
  - It ensures the username is not empty. If it is, an error response with a 400 status code is sent, along with a message indicating the necessity of a username.
  - It verifies that the new username contains only letters, numbers, and underscores using a regular expression. If it doesn't comply, an error response with a 400 status code is sent, along with a message indicating the allowed characters.
  - It confirms whether the new username is different from the current one. If they match, an error response with a 400 status code is sent, indicating that the new username is identical to the current one.
  - It checks if the new username meets length requirements (between 3 and 20 characters). If it doesn't, an error response with a 400 status code is sent, indicating the length requirement.
  - It validates the user's authentication by checking if the user ID exists. If not authenticated, an error response with a 401 status code is sent, indicating the lack of authentication.
- If all checks pass, the function initiates the username change by creating a new User object:
  - If the current username is not empty and differs from the new username, the function attempts to change the username:
    - If successful, a success response with a 200 status code is sent, indicating the username update.
    - If unsuccessful, an error response with a 400 status code is sent, along with the corresponding error message.
  - If the current username is empty, the function attempts to add the new username:
    - If successful, a success response with a 200 status code is sent, indicating the username addition.
    - If unsuccessful, an error response with a 400 status code is sent, along with the corresponding error message.
  - If the current username is not empty and matches the new username, an error response with a 400 status code is sent, indicating that the new username is identical to the current one.

### POST Request: [https://api.imperfectgamers.org/auth](https://api.imperfectgamers.org/auth)

#### Expected Body:

```json
{
	"username": "",
	"password": ""
}
```

The `authenticate` function is responsible for authenticating a user. It begins by logging the start of the authentication process and attempts to parse the request body, which should be a JSON string containing the user's credentials (username and password).

#### Functionality:

- The function checks that the required fields (username and password) are present in the request body. It then extracts the username and password and creates a new User object, passing the database connection to the constructor.
- It determines whether the provided identifier is an email or a username by calling the appropriate method of the User object. If a password is returned, it indicates the identifier is an email. Otherwise, it assumes it's a username.
- If the identifier is neither an email nor a username, the function logs a warning message and sends an error response to the client, indicating that the user was not found.
- Next, it verifies the provided password against the password retrieved from the database. If they match, it logs a success message and creates a new Device object, passing the database connection and logger to the constructor.
- It then attempts to save the device information in the database and associate the device with the user's login. If successful, it tries to generate and save a token for the user.
- If the token is successfully saved, it sends a success response to the client, including the token and the user's ID in the response body. It also logs the successful end of the authentication process.

- If any unexpected exceptions occur during the authentication process, the function catches them, logs an error message, and sends an error response to the client.

- If the provided password does not match the password in the database, the function logs a failed login attempt and sends an error response to the client, indicating that the provided username or password is invalid.

