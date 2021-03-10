# Secret Example

In this example we illustrate a production service that returns secrets. It consists of two services:

1. [secrets-service](services/secrets/index.php): this gets the secrets from the configured secrets store and returns it.
2. [client-service](services/client/index.php): Invokes the secret service and returns the secret. This is exposed locally.

For development (with docker-compose), secrets are stored in [secrets.json](components/secrets.json)

## Running the example

### Docker Compose

> Requirements:
> - Docker Compose
> - Docker
> - `make`
> - `jq` (optional)

1. Run `make` to build the images
2. Run `make start` to start the containers in the background
3. Run `curl -s localhost:8080/run | jq .` to call the client-service and return the secrets.

### Kubernetes

> Requirements
> - A configured kubernetes cluster
> - Dapr installed on the cluster
> - `make`
> - A Docker Hub user

1. Update [the `.env` file](.env) with your Docker Hub username  
2. Run `make push` to build and push the images
3. Run `make deploy` to deploy to Kubernetes
4. Wait for the pods to be running: `watch kubectl get pods`
4. In another terminal run `kubectl port-forward deployment/client 8080:80`
5. View the secrets using `curl -s localhost:8080/run | jq .` to call the client-service and return the secrets.
