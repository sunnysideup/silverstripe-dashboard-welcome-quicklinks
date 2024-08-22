# example

[Link to example.png](example.png)

# Defaults

There is a Default implementation of this interface called `DefaultDashboardProvider`. You can extend this class and add your own methods to it.
However, if you do not want the default ones, you can turn it off like this:

```yml
Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuicklinks:
  use_default_dashboard_provider: false
```

You can add your own classes that implement the `DashboardWelcomeQuickLinksProvider` interface.

These classes should then have the following method:

```php

    public function provideDashboardWelcomeQuickLinks() : array
    {
        return
            [
                'MyGroupCode' => [
                    'Title' => 'Cool Stuff',
                    'SortOrder' => 12,
                    'Colour' => '#555777',
                    'IconClass' => 'font-icon-p-virtual',
                    'Items' => [
                        [
                            'Title' => 'Click here',
                            'Link' => 'https://docs.silverstripe.org',
                            'OnClick' => 'alert("Are you sure?")',
                            'Script' => '',
                            'Class' => 'more-item', // more item means that it is hidden by default
                            'IconClass' => 'font-icon-p-virtual',
                            'Target' => '_blank',
                        ],
                        [
                            'Title' => 'Click here',
                            'Link' => MyModelAdmin::class,
                        ],
                        [
                            'Title' => 'Just a note',
                        ],
                    ],
                ],
            ];

    }


```

You can do this in many different places as you see fit.

# default load of CMS

If you would like the dashboard to load by default, then you can add the code below.

```yml
SilverStripe\Admin\AdminRootController:
  default_panel: Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuickLinks
```
