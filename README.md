# ThemePlate Process

## Usage

```php
use ThemePlate\Process\Async;

// Instantiate
$background = new Async( function() {
	long_running_task();
} );

// Dispatch
$background->dispatch();
```

### new Async( $callback_func, $callback_args )

Execute a heavy one-off task via a non-blocking request

- **$callback_func** *(callable)(Required)* Function to run asynchronously
- **$callback_args** *(array)(Optional)* Parameters to pass in the callback. Default `null`

### ->dispatch()

Fire off the process in the background instantly

### ->then( $callback )
### ->catch( $callback )

Chainable methods to handle success or error

- **$callback** *(callable)(Optional)*

---

```php
use ThemePlate\Process\Tasks;

$chores = new Tasks( 'my_day' );

$chores->add( 'first_task', array( 'this', 'that' ) );
$chores->add( function() {
	another_task();
} );
```

### new Tasks( $identifier )

- **$identifier** *(string)(Required)* Unique identifier

### ->add( $callback_func, $callback_args )

- **$callback_func** *(callable)(Required)* Function to run
- **$callback_args** *(array)(Optional)* Parameters to pass. Default `null`

### ->remove( $callback_func, $callback_args )

- **$callback_func** *(callable)(Required)* Supposed function to run
- **$callback_args** *(array)(Optional)* The parameters passed. Default `null`

### ->clear()

Remove all currently listed task

### ->limit( $number )

- **$number** *(int)(Required)* Number of task per run

### ->every( $second )

- **$second** *(int)(Required)* Interval between runs

### ->report( $callback )

- **$callback** *(callable)(Required)* To run after completion
