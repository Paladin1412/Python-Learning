from threading import Thread

a = []


def ad(n):
    for i in range(n):
        a.append(n)


t1 = Thread(target=ad,args=(100,))
t2 = Thread(target=ad,args=(200,))

t1.start()
t2.start()
t1.join()
t2.join()
print(len(a))
