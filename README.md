# Copy Feed

## Challenge Description

- Create a PHP command line tool which copies a table entry (incl. strictly required data) and accepts an option that would also copy additional entries. Also, write unit tests for the CLI tool.

## Project Setup
### 1. Pre-Requisites
- PHP >= 8.2,
- Composer
- postgres >= 15.14

#### To check if PHP is installed and running:
```shell
  php -v
```

#### To check if Composer is installed and running:
```shell
  composer --version
```

#### To check if Postgres is installed and running:
##### For Mac: 
To check Version
```shell
  postgres --version
```
To check running status
```shell
  brew services info postgresql@15 
```

### 2. Project Startup
1. #### Clone the repository:
    ```shell
    git clone https://github.com/slsawhney/copy_table.git
    ```
2. #### Navigate to the project folder:

    ```shell
    cd copy_table
    ```

3. #### Install dependencies:

    ```shell
    composer install
    ```

4. #### Set up the environment configuration:

    ```shell
    cp .env.example .env
    ```
   Edit the .env file and set the database credentials for your Local environment and Production environment.


5. #### Database Table Query for Testing purpose only:
   Queries are located in the file table_queries.sql.


6. #### Commands to copy the data:
   Make the copy wrapper executable:
   ```shell
   chmod +x bin/copy
   ```
7. ##### Commands:

   ##### Copy a give Feed ID
   ```shell
   php bin/copy app:copy-feed 123
   ```
   
   ##### Copy only instagram and feed for a given Feed ID
   ```shell
   php bin/copy app:copy-feed --only=instagram 123 
   ```

   ##### Copy only tiktok and feed for a given Feed ID
   ```shell
   php bin/copy app:copy-feed --only=tiktok 123 
   ```
    
   ##### Copy Only instagram for given Feed Id with 5 posts
   ```shell 
   php bin/copy app:copy-feed --only=instagram --include-posts=5 123 
   ```
   ##### Copy Only tiktok for given Feed Id with 5 posts
   ```shell
   php bin/copy app:copy-feed --only=tiktok --include-posts=5 123 
   ```
7. #### Test Cases:
   To Run the test cases run the following command:
   ```shell
   php vendor/bin/phpunit
   ```

8. #### PHPCS
   To Run php_code_sniffer
   ```shell
   php vendor/bin/phpcs --standard=PSR12 src
   ``` 
