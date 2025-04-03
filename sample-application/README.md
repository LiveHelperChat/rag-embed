# Sample flow how to integrate Vector Storage into Live Helper Chat

At this moment this sample app embeds one document only at a time. You can extend and adopt to your needs.

## Run ChromaDB

```
docker-compose -f docker-compose.yml up -d
```

* Navigate browser to http://localhost:8010/docs
* Create Tenant http://localhost:8010/docs#/default/create_tenant-v2 E.g name - `lhc`

```shell
curl -X 'POST' \
  'http://127.0.0.1:8010/api/v2/tenants' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
  "name": "lhc"
}'
```

* Create Database http://localhost:8010/docs#/default/create_database-v2 by entering Tenant name `lhc`

```shell
curl -X 'POST' \
  'http://127.0.0.1:8010/api/v2/tenants/lhc/databases' \
  -H 'accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
  "name": "lhc"
}'
```

To list databases

```
curl -X 'GET' 'http://localhost:8010/api/v2/tenants/lhc/databases' -H 'accept: application/json'
```

To list collections

```
curl -X 'GET' 'http://localhost:8010/api/v2/tenants/lhc/databases/lhc/collections' -H 'accept: application/json'
```

## Run embeding docker image 

Instrutions here

https://github.com/LiveHelperChat/rag-embed?tab=readme-ov-file#introduction

## Generate embeding file from your document

E.g in the `sample-application` folder I just put my documentation file. Documentation file was generated using 

```
docker run --rm -v $(pwd)/output:/app/output -v $(pwd)/cache:/app/cache remdex/crawler-to-md --url https://doc.livehelperchat.com --exclude "/docs/hooks"
```

More information at https://github.com/LiveHelperChat/crawler-to-md

```php
php embed_documents.php httpsdoc.livehelperchat.com.md
```

After that in this folder you will see `output.json` file generated. Generation for 1.3 MB files takes about 30 seconds.

## Store embedings in Chroma DB

```php
php store_embeddings.php
```

You should see something like

```
Embedding dimension: 384
Using existing collection: lhc with id bb308c00-4392-4f8b-ba81-6f722221fc64
Successfully stored 3459 embeddings to ChromaDB
```

Write down your collection id `bb308c00-4392-4f8b-ba81-6f722221fc64`

## Search by embedings

Last argument is your collection ID

```
php search_embeddings.php "Your search query" "Collection ID"
```

## Incorporate vector storage and embeding API into LHC for self hosting solution.

Flow should look like

* First query should go to embeding server and embedings retrieved
* Embedings should be send to Vector Database Chroma DB
* Embedings results merged into single text
* Embedings should be send to LLM for final answer
