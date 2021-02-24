FROM php:8-cli AS php-plugin
RUN apt-get update && apt-get install -y git
ENV GRPC_VERSION=v1.36.0
WORKDIR /
RUN git clone -b $GRPC_VERSION https://github.com/grpc/grpc
WORKDIR /grpc
RUN git submodule update --init
RUN apt-get install -y curl gnupg
RUN curl -fsSL https://bazel.build/bazel-release.pub.gpg | gpg --dearmor > bazel.gpg
RUN mv bazel.gpg /etc/apt/trusted.gpg.d/
RUN echo "deb [arch=amd64] https://storage.googleapis.com/bazel-apt stable jdk1.8" > /etc/apt/sources.list.d/bazel.list
RUN apt-get update && apt-get install -y bazel python
ENV CC=gcc
RUN bazel build @com_google_protobuf//:protoc
RUN bazel build src/compiler:grpc_php_plugin

ENV PATH="/grpc/bazel-bin/external/com_google_protobuf:${PATH}"
RUN protoc --version

ENV DAPR_VERSION=v1.0.0
WORKDIR /
RUN git clone -b $DAPR_VERSION https://github.com/dapr/dapr.git && mkdir -p /php
WORKDIR /dapr/dapr/proto
RUN protoc \
    --proto_path=. \
    --proto_path=./../.. \
    --proto_path=./../../../grpc/third_party/protobuf/src \
    --php_out=/php \
    --grpc_out=/php \
    --plugin=protoc-gen-grpc=./../../../../../grpc/bazel-bin/src/compiler/grpc_php_plugin \
    runtime/v1/dapr.proto
FROM scratch AS php-protos
COPY --from=php-plugin /php /grpc-generated
