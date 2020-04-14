# The Big Give Mailer

Mailer is a [Slim](https://www.slimframework.com/) PHP microservice for queueing, rendering
and sending emails.

* [Run the app](#Run-the-app)
* [Run unit tests](#Run-unit-tests)
* [API](#API)
* [Service dependencies](#Service-dependencies)
* [Scripts and Docker](#Scripts-and-Docker)
* [Code structure](#Code-structure)
* [Deployment](#Deployment)

## Run the app

You should usually use Docker to run the app locally in an easy way, with the least possible
configuration and the most consistency with other runtime environments - both those used
when the app is deployed 'for real' and other developers' machines.

### Prerequisites

In advance of the first app run:

* [get Docker](https://www.docker.com/get-started)
* copy `.env.example` to `.env` and change any values you need to

### Start the app

To start the app and its dependencies locally:

    docker-compose up -d web

### First run

To get PHP dependencies and an initial data in structure in place, you'll need to run these once:

    docker-compose exec web composer install

If dependencies change you may occasionally need to re-run the `composer install`.

## Run unit tests

Once you have the app running, you can test with: 

    docker-compose exec web composer run test

Linting is run with

    docker-compose exec web composer run lint:check

To understand how these commands are run in CI, see [the CirleCI config file](./.circleci/config.yml).

## API

Actions are annotated with [swagger-php](https://github.com/zircote/swagger-php) -ready doc block annotations.

Generate OpenAPI documentation corresponding to your local codebase with:

    docker-compose exec web composer run docs

The latest stable docs should be copied to their [live home on SwaggerHub](https://app.swaggerhub.com/apis/thebiggive/mailer/)
after any changes.

## Service dependencies

### Redis

Redis is used to queue messages. The two reasons to use a queue like this are:
 
 * Fast response times. While TBG Mailer is healthy it responds quickly, regardless of the
   speed of SES API responses or any issues with our account standing that might delay the actual
   sending of emails.
 * Reliability. If an SES send fails for any reason, the message goes back on the queue and will
   be sent once we / Amazon fix the problem.

Message properties are the key placeholders needed for the template / subject line, and the
recipient's email address.

## Scripts and Docker

You can see how custom scripts are defined in [`composer.json`](./composer.json). For now there
is just one, `mailer:send-emails`, which starts a long-lived process to listen to the queue for
any emails that are ready to render and send.

### Discovering more about scripts

There is a Composer script `list-commands` which calls `list` to read the registered commands.
With an already-running Docker `web` container, you can run

    docker-compose exec web composer list-commands

Currently we define no custom commands, instead pulling in the Symfony dependencies
necessary to use the `symfony/messenger` component and its built-in commands.

### Running scripts locally

To run a consumer worker similarly to how our ECS tasks will (distinct from
the `web` container), run:

    docker-compose run --rm consumer

As you can see in `docker-compose.yml`, this is just a shortcut to get a standalone
CLI process to run the long-running worker task defined with
`composer run messenger:consume`.

### How tasks run on staging & production

[ECS](https://aws.amazon.com/ecs/) task invocations are configured to keep at least one consumer task
running. Tasks get their own ECS cluster/service to run on, independent of the web cluster.

## Code structure

Mailer's code is organisation is loosely based on the [Slim Skeleton](https://github.com/slimphp/Slim-Skeleton),
and elements like the error & shutdown handlers and much of the project structure follow its conventions.

Generally this structure follows normal conventions for a modern PHP app:

* Dependencies are defined (only) in `composer.json`, including PHP version and extensions
* Source code lives in [`src`](./src)
* PHPUnit tests live in [`tests`](./tests), at a path matching that of the class they cover in `src`
* Slim configuration logic and routing live in [`app`](./app)

### Configuration in `app`

* [`dependencies.php`](./app/dependencies.php): this sets up dependency
  injection (DI) for the whole app. This determines how every class gets the stuff it needs to run. DI is super
  powerful because of its flexibility (a class can say _I want a logger_ and not worry about which one), and typically
  avoids objects being created that aren't actually needed, or being created more times than needed. Both of these files
  work the same way - they are only separate for cleaner organisation.

  We use Slim's [PSR-11](https://www.php-fig.org/psr/psr-11/) compliant Container with [PHP-DI](http://php-di.org/).
  There's an [overview here](https://www.slimframework.com/docs/v4/concepts/di.html) of what this means in the context
  of Slim v4.

  With PHP-DI, by tuning dependencies to be more class-based we could potentially eliminate some of our explicit
  depenendency definitions in the future by taking better advantage of [autowiring](http://php-di.org/doc/autowiring.html).
* [`routes.php`](./app/routes.php): this small file defines every route exposed on the web, and every authentication
  rule that applies to them. The latter is controlled by [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware and
  is very important to keep in the right place!
  
  Slim uses methods like `get(...)` and `put(...)` to hook up specific HTTP methods to classes that should be invoked.
  Our `Action`s' boilerplate is set up so that when the class is invoked, its `action(...)` method does the heavy
  lifting to serve the request.

  `add(...)` is responsible for adding middleware. It can apply to a single route or a whole group of them. Again, this
  is how we make routes authenticated. **Modify with caution!**
* [`settings.php`](./app/settings.php): you won't normally need to do much with this directly because it mostly just
  re-structures environment variables found in `.env` (locally) or env vars loaded from a secrets file (on ECS), into
  formats expected by classes we feed config arrays.

### Important code

The most important areas to explore in `src` are:

* [`Application\Actions`](./src/Application/Actions): all classes exposing APIs to the world. Anything invoked
  directly by a Route should be here.
* [`Application\Commands`](./src/Application/Commands): all classes extending `Command` (we use the [Symfony Console](https://symfony.com/doc/current/console.html)
  component). Every custom script we invoke and anything extending `Command` should be here.

## Deployment

Deploys are rolled out by [CirlceCI](https://circleci.com/), as [configured here](./.circleci/config.yml), to an
[ECS](https://aws.amazon.com/ecs/) cluster, where instances run the app live inside Docker containers.

As you can see in the configuration file,

* `develop` commits trigger deploys to staging and regression environments; and
* `master` commits trigger deploys to production

These branches are protected on GitHub and you should have a good reason for skipping any checks before merging to them!

### ECS runtime containers

ECS builds have two additional steps compared to a local run:

* during build, the [`Dockerfile`](./Dockerfile) adds the AWS CLI for S3 secrets access, pulls in the app files, tweaks
  temporary directory permissions and runs `composer install`. These things don't happen automatically with the [base
  PHP image](https://github.com/thebiggive/docker-php) as they don't usually make sense for local runs;
* during startup, the entrypoint scripts load in runtime secrets securely from S3 and ensure some cache directories have
  appropriate permissions. This is handled in the two `.sh` scripts in [`deploy`](./deploy) - one for web instances and
  one for tasks.

### Phased deploys

Other AWS infrastructure includes a load balancer, and ECS rolls out new app versions gradually to try and keep a
working version live even if a broken release is ever deployed. Because of this, new code may not reach all users until
about 30 minutes after CircleCI reports that a deploy is done. You can monitor this in the AWS Console.

When things are working correctly, any environment with at least two tasks in its ECS Service should get new app
versions with no downtime. If you make schema changes, be careful to use a [parallel change (expand / contract)](https://www.martinfowler.com/bliki/ParallelChange.html)]
pattern to ensure this remains true.
