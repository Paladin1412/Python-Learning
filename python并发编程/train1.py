from concurrent.futures import ThreadPoolExecutor,as_completed

def printk(t):
    print(t)
ec = ThreadPoolExecutor(max_workers=1)
task = []
[task.append(i) for i in range(4)]
at = [ec.submit(printk,(i)) for i in task]
for future in as_completed(at):
    ret = future.result()
    print(ret)