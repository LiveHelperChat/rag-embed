#!/bin/sh

cd /rag-embed/sample-application

rm -rf ./cache
rm -rf ./output

docker run --rm -v $(pwd)/output:/app/output -v $(pwd)/cache:/app/cache remdex/crawler-to-md --url https://doc.livehelperchat.com --exclude "/docs/hooks"

# Convert documents to embeddings outputs output.json

cd /rag-embed/sample-application && php ./embed_documents.php ./output/doc_livehelperchat_com/httpsdoc.livehelperchat.com.md

# Store documents in ChromaDB

cd /rag-embed/sample-application && php ./store_embeddings.php