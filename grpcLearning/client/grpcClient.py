from __future__ import print_function

from grpcLearning.server.hello_pb2 import *
from grpcLearning.server.hello_pb2_grpc import *


def run():
    channel = grpc.insecure_channel('localhost:50051')
    stub = GreeterStub(channel)
    response = stub.SayHello(HelloRequest(name='world'))
    print("Greeter client received: " + response.message)


if __name__ == '__main__':
    run()