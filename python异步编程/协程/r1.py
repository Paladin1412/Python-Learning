def e():
    yield from range(100)

def w():
    for i in e():
        print(i)

w()