def simple_coroutine():
    print('->coroutine started')
    x=yield
    print('->courotine recived:',x)
# sc = simple_coroutine()
# print(next(sc))#预激协程
# sc.send(42)
# print(next(sc))

def simple2(a):
    print('-> Started a = ',a)
    b=yield a
    print('->Received: b=',b)
    c=yield a+b
    print('->Received: c=',c)

# s2 = simple2(14)
# from inspect import getgeneratorstate
# print(getgeneratorstate(s2))#获取协程状态
# next(s2)
# print(getgeneratorstate(s2))
# s2.send(28)
# print(s2)

def s3_cal_average():
    total = 0.0
    count = 0
    average = None
    while True:
        term = yield average
        total +=term
        count+=1
        average=total/count
        print(average)
s3 = s3_cal_average()
next(s3)
s3.send(1)
s3.send(2)