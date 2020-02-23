# ThemePlate Process

## Usage

```php
use ThemePlate\Process;

// Instantiate
$background = new Process( function() {
	long_running_task();
} );

// Dispatch
$background->dispatch();
```

### new Process( $callback_func, $callback_args )

Execute a heavy one-off task via a non-blocking request

- **$callback_func** *(callable)(Required)* Function to run asynchronously
- **$callback_args** *(array)(Optional)* Parameters to pass in the callback. Default `null`

### ->dispath()

Fire off the process in the background instantly

### ->then( $callback )
### ->catch( $callback )

Chainable methods to handle success or error

- **$callback** *(callable)(Optional)*
