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

---

```php
use ThemePlate\Tasks;

$chores = new Tasks( 'my_day' );

$chores->add( 'first_task', array( 'this', 'that' ) );
$chores->add( function() {
	another_task();
} );

$chores->execute();
```

### new Tasks( $identifier )

- **$identifier** *(string)(Required)* Unique identifier

### ->add( $callback_func, $callback_args )

- **$callback_func** *(callable)(Required)* Function to run
- **$callback_args** *(array)(Optional)* Parameters to pass. Default `null`

### ->limit( $number )

- **$number** *(int)(Required)* Number of task per run

### ->every( $second )

- **$second** *(int)(Required)* Interval between runs

### ->report( $callback )

- **$callback** *(callable)(Required)* To run after completion
