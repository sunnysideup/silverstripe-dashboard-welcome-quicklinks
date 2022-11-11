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
                    'Items' => [
                        [
                            'Title' => 'Click here',
                            'Link' => 'https://docs.silverstripe.org',
                        ],
                    ],
                ],
            ];

    }


```
