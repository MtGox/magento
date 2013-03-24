# MtGox magento module

Integration of the MtGox payment method and of the Bitcoin currency.

### How to install/configure

1. Copy the app and lib folders into your magento root folder.
2. Clear every cache under System / Cache Management
3. Log out from the admin panel and then log in again
4. Configure the MtGox payment method in `System / Configuration, Sales > Payment Methods > MtGox Bitcoin`, fill in the API Secret + Key

### How to show the Bitcoin price besides the normal price in your catalog

1. Go to `System / Configuration, Catalog > MtGox Bitcoin`, and set "Show price in BTC" as "Yes".
2. Clear your cache

### How to enable the Bitcoin currency

1. Go to `System / Configuration, Advanced > System`. In Currency, select the Bitcoin currency.
2. Then configure on your website under `System / Configuration, General > Currency Setup` the Base Currency and Default Display Currency as Bitcoin, add as well Bitcoin in the allowed currencies.

### Known Issues

1. *When saving my API Secret + Key, I get an error message which says that the values are invalid.* Ignore it and save again.
2. *You override the native XML files in lib.* We don't have a choice, until Magento adds natively the Bitcoin currency.
3. *I don't see the Bitcoin currency in my country/language.* We have to add it manually, create an issue.
