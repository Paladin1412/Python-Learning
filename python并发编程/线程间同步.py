from threading import Lock,Thread
total=0
lock = Lock() #使用锁会影响性能，并且可能引发死锁 
def add():
    global total
    global lock
    for i in range(10000000):
        lock.acquire()
        total+=1
        lock.release()
def desc():
    global total
    global lock
    for i in range(10000000):
        lock.acquire()
        total -=1
        lock.release()
if __name__ == "__main__":
    t1 = Thread(target=add)
    t2 = Thread(target=desc)
    t1.start()
    t2.start()
    t1.join()
    t2.join()
    print(total)