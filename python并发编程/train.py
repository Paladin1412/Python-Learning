import threading
import time
from queue import Queue

def get_task(queue):
    while True:
        task = queue.get()
        print(task)
        if task ==8:
            break

def put_task(queue):
    lists=[]
    [lists.append(i) for i in range(10)]
    while True:
        for i in lists:
            time.sleep(1)
            queue.put(i)

if __name__ == '__main__':
    q = Queue(maxsize=1000)
    at = [ec.submit()]
    t1 = threading.Thread(target=get_task,args=(q,))
    t2 = threading.Thread(target=put_task,args=(q,))
    t1.start()
    t2.setDaemon(True)
    t2.start()
    t1.join()
