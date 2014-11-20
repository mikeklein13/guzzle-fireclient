#guzzle-fireclient

### Log to the Firebug console from internal microservices, as easily as an external one.

Use FireClient to ensure that calls made to microservices with [GuzzleHttp](http://guzzle.readthedocs.org/ "GuzzleHttp") will act as a [FirePHP](http://www.firephp.org/ "FirePHP") client, consuming the remote service's FirePHP headers and proxying to the original caller.


#### The Problem: Microservice calls are in the dark

- Client browser requests resource from Server A
- Server A builds resource for Client Browser by aggregating results from several internal microservices, hosted on servers B, C, D. Server A can not see work being performed by Servers B, C and D.
- Client Browser only receives FirePHP entries that Server A was directly performing
- Client is not able to gain any insight into work being performed by other services, making debug difficult.


#### The Solution

Access microservices with GuzzleHttp + FireClient plugin. Requests made with GuzzleHttp act as a FirePHP client, consume FirePHP headers and are proxied to the original caller.


#### Usage

```
use GuzzleHttp\Client;
use FireClient\Subscribers\WildFireSubscriber;

$guzzle     = new Client();
$subscriber = new WildfireSubscriber();

$guzzle->getEmitter()->attach( $subscriber );
```

#### Features

Supports:
- Log
- Info
- Warn
- Error
- Table
- Begin Group
- End Group

Partial Support:
- Trace
  * would attempt to capture a live backtrace, and is therefore only simulated via table
- Variable export

#### Contributions

- Must follow Behance's coding standards: https://github.com/behance/php-sniffs
- Must include tests

