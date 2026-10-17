<?php

require __DIR__ . '/project.inc';

// An app whose modules bind each lifetime and each kind of value, with an environment module and a nested namespace's
// module that overrides its parent's, beside the standard library's module, the classes it binds, Events and Scope, as
// a Composer project holds them. container_project compiles it with $files, loads it with container_load, and returns
// the project's folder.
function container_project(string $name, array $files = []): string
{
    $library = 'vendor/heyjordanparker/php-sharp-composer';
    $source = dirname(__DIR__, 3) . '/sharp/composer/library/Sharp';
    $classes = [];
    foreach ([...LIBRARY_MODULE, 'Events', 'Scope'] as $class) {
        $classes["$library/library/Sharp/$class.sharp"] = file_get_contents("$source/$class.sharp");
    }
    $root = compile_project($name, CONTAINER_APP + $files + $classes + [
        "$library/composer.json" => '{"name": "heyjordanparker/php-sharp-composer"}',
    ]);
    container_load($root);

    return $root;
}

// Reads the library's module of the project at $root as the library's autoloader does, and loads each class from its
// folder, a class of Sharp from the library's.
function container_load(string $root): void
{
    $library = "$root/vendor/heyjordanparker/php-sharp-composer/library";
    Sharp\Internal\readModule("$library/Sharp/Module.sharp");
    spl_autoload_register(function (string $class) use ($root, $library): void {
        $file = str_replace('\\', '/', $class) . '.sharp';
        $path = str_starts_with($class, 'Sharp\\') ? "$library/$file" : "$root/" . lcfirst($file);
        if (is_file($path)) {
            require $path;
        }
    });
}

const CONTAINER_APP = [
    'app/Mail/Mailer.sharp' => <<<'SHARP'
namespace App.Mail;

public interface Mailer
{
    string name();
}
SHARP,
    'app/Mail/SmtpMailer.sharp' => <<<'SHARP'
namespace App.Mail;

public class SmtpMailer : Mailer
{
    public string name() => "smtp";
}
SHARP,
    'app/Mail/QueueMailer.sharp' => <<<'SHARP'
namespace App.Mail;

public class QueueMailer : Mailer
{
    public string name() => "queue";
}
SHARP,
    'app/Mail/LogMailer.sharp' => <<<'SHARP'
namespace App.Mail;

public class LogMailer : Mailer
{
    public string name() => "log";
}
SHARP,
    'app/Pricing/PriceRule.sharp' => <<<'SHARP'
namespace App.Pricing;

public interface PriceRule
{
    string name();
}
SHARP,
    'app/Pricing/TaxRule.sharp' => <<<'SHARP'
namespace App.Pricing;

public class TaxRule : PriceRule
{
    public string name() => "tax";
}
SHARP,
    'app/Pricing/DiscountRule.sharp' => <<<'SHARP'
namespace App.Pricing;

public class DiscountRule : PriceRule
{
    public string name() => "discount";
}
SHARP,
    'app/Shop/Module.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.SmtpMailer;
import App.Mail.QueueMailer;
import App.Pricing.PriceRule;
import App.Pricing.TaxRule;
import App.Pricing.DiscountRule;

module
{
    singleton Mailer = SmtpMailer;
    singleton Mailer for SendReceipt = QueueMailer;
    transient List<PriceRule> = [TaxRule, DiscountRule];
    singleton Greeting(Environment environment) = new Greeting(environment.variable("SHARP_GREETING") ?? "none");
    scoped Visit = Visit;
    transient Stamp = Stamp;
    transient Checkout = Checkout;
    transient SendReceipt = SendReceipt;
}
SHARP,
    'app/Shop/Module.Production.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Mail.Mailer;
import App.Mail.LogMailer;

module : App.Shop
{
    singleton Mailer = LogMailer;
}
SHARP,
    'app/Shop/Checkout.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Mail.Mailer;
import App.Pricing.PriceRule;

public class Checkout
{
    public Checkout(private Mailer mailer, private List<PriceRule> rules, private Greeting greeting, private Visit visit, private Stamp stamp) { }

    public Mailer usedMailer() => this.mailer;

    public List<PriceRule> usedRules() => this.rules;

    public Greeting usedGreeting() => this.greeting;

    public Visit usedVisit() => this.visit;

    public Stamp usedStamp() => this.stamp;
}
SHARP,
    'app/Shop/SendReceipt.sharp' => <<<'SHARP'
namespace App.Shop;

import App.Mail.Mailer;

public class SendReceipt
{
    public SendReceipt(private Mailer mailer) { }

    public Mailer usedMailer() => this.mailer;
}
SHARP,
    'app/Shop/Greeting.sharp' => <<<'SHARP'
namespace App.Shop;

public class Greeting
{
    public Greeting(private string text) { }

    public string usedText() => this.text;
}
SHARP,
    'app/Shop/Visit.sharp' => <<<'SHARP'
namespace App.Shop;

public class Visit
{
}
SHARP,
    'app/Shop/Stamp.sharp' => <<<'SHARP'
namespace App.Shop;

public class Stamp
{
}
SHARP,
    'app/Shop/Admin/Module.sharp' => <<<'SHARP'
namespace App.Shop.Admin;

import App.Mail.Mailer;
import App.Mail.QueueMailer;

module
{
    singleton Mailer = QueueMailer;
    transient Panel = Panel;
}
SHARP,
    'app/Shop/Admin/Panel.sharp' => <<<'SHARP'
namespace App.Shop.Admin;

import App.Mail.Mailer;
import App.Shop.Greeting;

public class Panel
{
    public Panel(private Mailer mailer, private Greeting greeting) { }

    public Mailer usedMailer() => this.mailer;

    public Greeting usedGreeting() => this.greeting;
}
SHARP,
];
