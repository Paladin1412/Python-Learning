def simple_coroutine():
    print('->coroutine started')
    x=yield
    print('->courotine recived:',x)

sc = simple_coroutine()
print(next(sc))
sc.send(42)
print(next(sc))