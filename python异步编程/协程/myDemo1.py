def foo():
    print("s")
    while True:
        res = yield 4
        print("res",res)

g = foo()
print(next(g))
# print(next(g))
print(g.send(10))

def consumer():
    while True:
        res = yield
        print("消费：",res)

def produce(c):
    for i in range(1,10):
        print("produce:",i)
        c.send(i)


c= consumer()
next(c)
produce(c)