# MtGox magento module

Integration of the MtGox payment method and of the Bitcoin currency.

### How to install/configure

1. Copy the app and lib folders into your magento root folder. Ignore the Mtgox_Bitcoin.xml and package.xml files, they are there only for development purposes.
2. Clear every cache under System / Cache Management
3. Log out from the admin panel and then log in again
4. Configure the MtGox payment method in `System / Configuration, Sales > Payment Methods > MtGox Bitcoin`, fill in the API Secret + Key

### How to show the Bitcoin price beside the normal price in your catalog

1. Go to `System / Configuration, Catalog > MtGox Bitcoin`, and set "Show price in BTC" as "Yes".
2. Clear your cache

### How to enable the Bitcoin currency

1. Go to `System / Configuration, Advanced > System`. In Currency, select the Bitcoin currency.
2. Then configure on your website under `System / Configuration, General > Currency Setup` the Base Currency and Default Display Currency as Bitcoin, add as well Bitcoin in the allowed currencies. **This will switch the previous currency you had configured to Bitcoin**.

Please keep in mind that there won't be any conversion from your previous currency to Bitcoin. We consider that you will create or import the products with the proper Bitcoin price.

### Known Issues

1. *You override the native XML files in lib.* We don't have a choice, until Magento adds natively the Bitcoin currency.
2. *I don't see the Bitcoin currency in my country/language.* We have to add it manually, create a Github Issue and we will take care of it.