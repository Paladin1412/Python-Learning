import time
def step():
    print("->now:")

    a = yield f()
    print("->now",a)
    a = yield 8
    print("->now",a)
    a = yield 8
    print("->now",a)
def f():
    time.sleep(2)
    return 4
s = step()
s.send(None)
print(next(s))
