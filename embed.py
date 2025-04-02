#!/usr/bin/env python3
from langchain.embeddings import HuggingFaceEmbeddings
from langchain.text_splitter import RecursiveCharacterTextSplitter
from langchain.schema import Document  # Import the Document class

import os
import time
import json
from flask import Flask, request, jsonify

app = Flask(__name__)

embeddings_model_name = os.environ.get("EMBEDDINGS_MODEL_NAME", "all-MiniLM-L6-v2")

# Parse the command line arguments
embeddings = HuggingFaceEmbeddings(model_name=embeddings_model_name)

# Initialize the text splitter
text_splitter = RecursiveCharacterTextSplitter(
    chunk_size=500,  # Number of characters per chunk
    chunk_overlap=50,  # Overlap between chunks
    # separators=["\n\n", "\n", " ", ""]  # Splitting separators (default)
)

@app.route('/embed_query', methods=['POST'])
def process_conversation():
    try:
        data = request.json
        query_text = data.get('query', '')
        embeddings_vector = embeddings.embed_query(query_text)
        return jsonify({
            "embed": embeddings_vector
        })
    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route('/embed_documents', methods=['POST'])
def embed_documents():
    try:
        data = request.json
        documents = data.get('docs', [])

        if not documents:
            return jsonify({"error": "Documents are required"}), 400

        # Create Document objects with metadata to track the original document ID
        document_objects = [
            Document(page_content=doc, metadata={"source": doc_id})
            for doc_id, doc in enumerate(documents)
        ]

        # Split documents into chunks
        split_documents = text_splitter.split_documents(document_objects)

        # Extract text chunks, track which original document they belong to, and store chunk text
        text_chunks = []
        chunk_texts = []  # To store the text of each chunk
        document_mapping = []  # To store mapping of document_id to embedding indices
        current_embedding_index = 0

        for doc_id, doc in enumerate(document_objects):
            # Find all chunks that belong to this document
            chunks_for_doc = [
                chunk for chunk in split_documents
                if chunk.metadata.get("source") == doc_id
            ]
            num_chunks = len(chunks_for_doc)

            # Append chunks to the text_chunks and chunk_texts lists
            for chunk in chunks_for_doc:
                text_chunks.append(chunk.page_content)
                chunk_texts.append(chunk.page_content)

            # Record the embedding indices for this document
            document_mapping.append({
                "document_id": doc_id,
                "embedding_ids": list(range(current_embedding_index, current_embedding_index + num_chunks))
            })

            # Update the current embedding index
            current_embedding_index += num_chunks

        # Generate embeddings for all chunks
        document_embeddings = embeddings.embed_documents(text_chunks)

        # Prepare the response
        response = {
            "embeddings": document_embeddings,
            "chunk_texts": chunk_texts,  # Include chunk texts in the response
            "document_indices": document_mapping
        }

        return jsonify(response)
    except Exception as e:
        return jsonify({"error": str(e)}), 500


if __name__ == "__main__":
    app.run(host='0.0.0.0', port=5000)