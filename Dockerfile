# Use the official Python 3.10.11 image
FROM python:3.10.15-slim

RUN apt-get update && apt-get upgrade -y && apt-get install -y --no-install-recommends python3 python3-pip ninja-build libopenblas-dev build-essential
RUN apt-get update && apt-get install -y git

# Set the working directory in the container
WORKDIR /app

COPY requirements.txt /app/

# Install the necessary Python packages
RUN pip install --no-cache-dir  -r requirements.txt

RUN pip install --no-cache-dir numpy flask
RUN pip install --no-cache-dir sentence_transformers

# Copy the Python script and embedding file into the container
# If you want to have everything in image uncomment those
# COPY privateGPTRelated.py /app/
COPY embed.py /app/
# pip install --upgrade huggingface_hub==0.25.2

# Expose the port the app runs on
EXPOSE 5000

# Command to run your Python script
CMD ["python", "/app/embed.py"]