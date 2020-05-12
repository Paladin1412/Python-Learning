from collections import namedtuple
ret = namedtuple('Result','count average')
def averager():
    total=0.0
    count=0
    average = None
    while True:
        term = yield
        if term is None:
            break
        total+=term
        count+=1
        average = total/count
    return ret(count,average)

a = averager()
next(a)
a.send(11)
a.send(13)
a.send(None)