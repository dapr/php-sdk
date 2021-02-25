FROM php:8-cli AS grpc-sources
RUN apt-get update && apt-get install -y git
ENV GRPC_VERSION=v1.36.0
WORKDIR /
RUN git clone -b $GRPC_VERSION https://github.com/grpc/grpc
WORKDIR /grpc
RUN git submodule update --init

FROM grpc-sources AS grpc-builder
RUN apt-get install -y cmake
RUN mkdir -p cmake/build
WORKDIR /grpc/cmake/build
RUN cmake ../.. -DBUILD_SHARED_LIBS=ON -DgRPC_INSTALL=ON -DCMAKE_BUILD_TYPE=Release
RUN make -j$(nproc)
RUN mkdir -p /libgpr
RUN make DESTDIR=/libgpr install

FROM php:8-cli AS php-grpc
RUN apt-get update && apt-get install -y git
COPY --from=grpc-builder /libgpr /
COPY --from=grpc-builder /grpc/third_party/protobuf/src /protobuf
RUN ldconfig && protoc --version

FROM php-grpc AS dapr-protobuf-builder
ENV DAPR_VERSION=v1.0.0
WORKDIR /
RUN git clone -b $DAPR_VERSION https://github.com/dapr/dapr.git
WORKDIR /dapr

FROM dapr-protobuf-builder AS dapr-client-proto
RUN mkdir -p /php
RUN protoc \
    --proto_path=. \
    --proto_path=/protobuf \
    --php_out=/php \
    --grpc_out=/php \
    --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
    dapr/proto/runtime/v1/dapr.proto
RUN protoc \
    --proto_path=. \
    --proto_path=/protobuf \
    --php_out=/php \
    --grpc_out=/php \
    --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
    dapr/proto/common/v1/common.proto

FROM scratch AS php-protos
COPY --from=dapr-client-proto /php /grpc-generated

FROM php-grpc AS php-extension-builder
COPY --from=grpc-sources /grpc /grpc
RUN mkdir -p /php-extension
WORKDIR /grpc/src/php/ext/grpc
ENV grpc_root=/grpc
RUN phpize
RUN ./configure --enable-grpc="$grpc_root" --prefix=/php-extension
RUN make -j$(nproc)
RUN make install
RUN cp $(php-config --extension-dir)/grpc.so /php-extension

FROM php-grpc AS protobuf-ext-builder
COPY --from=grpc-sources /grpc /grpc
RUN mkdir -p /php-extension
WORKDIR /grpc/third_party/protobuf/php/ext/google/protobuf
RUN git checkout v3.15.2
RUN apt-get install -y wget unzip \
    && mkdir -p third_party/wyhash \
    && cd third_party/wyhash/ \
    && wget https://github.com/wangyi-fudan/wyhash/archive/wyhash_v5.zip \
    && unzip wyhash_v5.zip \
    && mv wyhash-wyhash_v5/* . \
    && rm -rf wyhash-wyhash_v5 \
    && rm -f wyhash_v5.zip
RUN phpize
RUN ./configure
RUN make -j$(nproc)
RUN make install
RUN cp $(php-config --extension-dir)/protobuf.so /php-extension

FROM scratch as php-extension
COPY --from=php-extension-builder /php-extension /
COPY --from=protobuf-ext-builder /php-extension /
