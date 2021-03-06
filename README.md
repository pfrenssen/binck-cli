# Command line interface for BinckBank

This project allows to do basic actions on the BinckBank online banking
software through the command line. I wrote this because I have RSI and cannot
use a mouse, and it is very difficult for me to use the website. It will also
be useful for people that have a visual disability, or of you simply prefer to
use the command line.

For the moment there is only support for exporting the transaction history.

BinckBank does not offer an API, so this works by emulating an able mouse user
clicking through the website.

## Requirements

This requires PHP 5.6 or higher, and either Selenium 2 or PhantomJS.

Running Selenium 2 locally:

```
$ java -jar selenium-server-standalone.jar 2>&1 >> /dev/null &
```

Running PhantomJS:

```
$ phantomjs --ssl-protocol=any --ignore-ssl-errors=true ./vendor/jcalderonzumba/gastonjs/src/Client/main.js 8510 1024 768 2>&1 >> /dev/null &
```

Running Selenium 2 using Docker:

```
$ docker run -d -p 4444:4444 --network=host --add-host="stream.login.binck.be:127.0.0.1" selenium/standalone-chrome
```

## Installation

First install the dependencies:
```
$ composer install
```

Then create a configuration file `config/config.yml` and in here store your
user name and password, and the browser driver you want to use (either
"selenium2" or "phantomjs"):

```
credentials:
  username: 'my username'
  password: 'my password'

mink:
  default_session: 'selenium2'
```

## Usage

```
$ ./binckcli
```
