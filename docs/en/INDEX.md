Any class can implement the `DashboardWelcomeQuickLinksProvider` interface.

These classes then have a method:

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
                            'Style' => '',
                            'IconClass' => 'font-icon-p-virtual',
                        ],                        
                        [
                            'Title' => 'Click here',
                            'Link' => MyModelAdmin::class,
                        ],
                    ],
                ],
            ];

    }


```

Go to `admin/go` by default:

```yml
SilverStripe\Admin\AdminRootController:
  default_panel: Sunnysideup\DashboardWelcomeQuicklinks\Admin\DashboardWelcomeQuickLinks
```
